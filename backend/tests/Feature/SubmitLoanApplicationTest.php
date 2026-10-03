<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Events\LoanApplicationChecked;
use App\Jobs\RunStopFactorChecks;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Отправка заявки и проверка стоп-факторов.
 *
 * Здесь два подхода к тестированию очереди:
 *  - Queue::fake(): job НЕ выполняется, проверяем только сам факт «положили в очередь»;
 *  - без fake: в тестах QUEUE_CONNECTION=sync (см. phpunit.xml), то есть job выполняется
 *    сразу внутри запроса — так проверяем весь сценарий от клика до результата.
 */
class SubmitLoanApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_incomplete_draft_cannot_be_submitted(): void
    {
        Queue::fake();

        $draft = LoanApplication::factory()->for($this->user)->blank()->create([
            'company_name' => 'ООО «Ромашка»',
        ]);

        $this->postJson("/api/loan-applications/{$draft->id}/submit")
            ->assertUnprocessable()
            // company_name заполнено — по нему ошибки быть не должно
            ->assertJsonValidationErrors(['inn', 'amount', 'term_months', 'purpose'])
            ->assertJsonMissingValidationErrors('company_name');

        $this->assertSame(ApplicationStatus::Draft, $draft->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_complete_draft_is_submitted_and_check_is_queued(): void
    {
        Queue::fake(); // подменяем очередь: задачи только запоминаются, не выполняются

        $draft = LoanApplication::factory()->for($this->user)->create();

        $this->postJson("/api/loan-applications/{$draft->id}/submit")
            ->assertAccepted() // 202
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.can_edit', false);

        $this->assertNotNull($draft->refresh()->submitted_at);

        // В очереди лежит именно наш job и именно с этой заявкой
        Queue::assertPushed(
            RunStopFactorChecks::class,
            fn (RunStopFactorChecks $job) => $job->application->is($draft),
        );
    }

    public function test_application_cannot_be_submitted_twice(): void
    {
        Queue::fake();

        $application = LoanApplication::factory()->for($this->user)->submitted()->create();

        $this->postJson("/api/loan-applications/{$application->id}/submit")->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_someone_elses_draft_cannot_be_submitted(): void
    {
        $foreign = LoanApplication::factory()->create();

        $this->postJson("/api/loan-applications/{$foreign->id}/submit")->assertForbidden();
    }

    public function test_clean_application_is_approved(): void
    {
        Event::fake([LoanApplicationChecked::class]); // это событие перехватываем, остальные работают как обычно

        $draft = LoanApplication::factory()->for($this->user)->create([
            'inn' => '7701234567',
            'amount' => 1_000_000,
        ]);

        // Очередь sync: job выполнится прямо внутри этого запроса
        $this->postJson("/api/loan-applications/{$draft->id}/submit")->assertAccepted();

        $draft->refresh();
        $this->assertSame(ApplicationStatus::Approved, $draft->status);
        $this->assertNotNull($draft->checked_at);

        // По одной строке на каждый стоп-фактор, все пройдены
        $this->assertSame(3, $draft->stopFactorResults()->count());
        $this->assertSame(0, $draft->stopFactorResults()->where('passed', false)->count());

        Event::assertDispatched(
            LoanApplicationChecked::class,
            fn (LoanApplicationChecked $event) => $event->application->is($draft),
        );

        // И в ответе API результаты теперь видны
        $this->getJson("/api/loan-applications/{$draft->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.stop_factors');
    }

    public function test_application_over_the_limit_is_rejected(): void
    {
        // config() можно менять на лету — в тестах это удобнее, чем править .env
        config(['loans.stop_factors.max_amount' => 100_000]);

        $draft = LoanApplication::factory()->for($this->user)->create(['amount' => 100_001]);

        $this->postJson("/api/loan-applications/{$draft->id}/submit")->assertAccepted();

        $this->assertSame(ApplicationStatus::Rejected, $draft->refresh()->status);
        $this->assertDatabaseHas('stop_factor_results', [
            'loan_application_id' => $draft->id,
            'code' => 'amount_limit',
            'passed' => false,
        ]);
    }

    public function test_blacklisted_inn_is_rejected(): void
    {
        config(['loans.stop_factors.blacklisted_inns' => ['7709999999']]);

        $draft = LoanApplication::factory()->for($this->user)->create(['inn' => '7709999999']);

        $this->postJson("/api/loan-applications/{$draft->id}/submit")->assertAccepted();

        $this->assertSame(ApplicationStatus::Rejected, $draft->refresh()->status);
        $this->assertDatabaseHas('stop_factor_results', [
            'loan_application_id' => $draft->id,
            'code' => 'blacklisted_inn',
            'passed' => false,
        ]);
    }

    public function test_second_application_for_the_same_inn_is_rejected(): void
    {
        // У другого пользователя уже есть одобренная заявка на этот ИНН
        LoanApplication::factory()->approved()->create(['inn' => '7701234567']);

        $draft = LoanApplication::factory()->for($this->user)->create(['inn' => '7701234567']);

        $this->postJson("/api/loan-applications/{$draft->id}/submit")->assertAccepted();

        $this->assertSame(ApplicationStatus::Rejected, $draft->refresh()->status);
        $this->assertDatabaseHas('stop_factor_results', [
            'loan_application_id' => $draft->id,
            'code' => 'duplicate_application',
            'passed' => false,
        ]);
    }
}
