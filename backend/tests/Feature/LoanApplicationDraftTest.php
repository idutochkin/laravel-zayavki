<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Работа с черновиками: создание, список, редактирование, удаление, права.
 */
class LoanApplicationDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** setUp выполняется перед каждым тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // «Считай, что все запросы в этом тесте идут от этого пользователя».
        // Токен при этом не создаётся — Sanctum просто подставляет пользователя.
        Sanctum::actingAs($this->user);
    }

    public function test_draft_can_be_saved_with_only_part_of_the_fields(): void
    {
        $response = $this->postJson('/api/loan-applications', [
            'company_name' => 'ООО «Ромашка»',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.company_name', 'ООО «Ромашка»')
            ->assertJsonPath('data.inn', null)
            ->assertJsonPath('data.can_edit', true);

        $this->assertDatabaseHas('loan_applications', [
            'user_id' => $this->user->id,
            'company_name' => 'ООО «Ромашка»',
            'status' => 'draft',
        ]);
    }

    public function test_draft_still_validates_the_format_of_what_was_sent(): void
    {
        $this->postJson('/api/loan-applications', ['inn' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inn' => 'ИНН должен состоять из 10 или 12 цифр.']);
    }

    public function test_status_and_owner_cannot_be_set_from_the_request(): void
    {
        // Защита от mass assignment: status и user_id нет ни в rules(), ни в #[Fillable]
        $stranger = User::factory()->create();

        $this->postJson('/api/loan-applications', [
            'company_name' => 'ООО «Хитрец»',
            'status' => 'approved',
            'user_id' => $stranger->id,
        ])->assertCreated();

        $application = LoanApplication::sole(); // ровно одна запись, иначе исключение

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame($this->user->id, $application->user_id);
    }

    public function test_list_contains_only_own_applications(): void
    {
        LoanApplication::factory()->count(2)->for($this->user)->create();
        LoanApplication::factory()->count(3)->create(); // чужие: фабрика создаст им другого владельца

        $this->getJson('/api/loan-applications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        LoanApplication::factory()->for($this->user)->create();
        LoanApplication::factory()->for($this->user)->approved()->create();

        $this->getJson('/api/loan-applications?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_someone_elses_application_is_forbidden(): void
    {
        $foreign = LoanApplication::factory()->create();

        $this->getJson("/api/loan-applications/{$foreign->id}")->assertForbidden(); // 403
        $this->putJson("/api/loan-applications/{$foreign->id}", ['purpose' => 'взлом'])->assertForbidden();
        $this->deleteJson("/api/loan-applications/{$foreign->id}")->assertForbidden();
    }

    public function test_draft_can_be_updated(): void
    {
        $draft = LoanApplication::factory()->for($this->user)->blank()->create();

        $this->putJson("/api/loan-applications/{$draft->id}", [
            'inn' => '7701234567',
            'amount' => 500000,
        ])
            ->assertOk()
            ->assertJsonPath('data.inn', '7701234567')
            ->assertJsonPath('data.amount', 500000);
    }

    public function test_submitted_application_cannot_be_changed_or_deleted(): void
    {
        $application = LoanApplication::factory()->for($this->user)->submitted()->create();

        $this->putJson("/api/loan-applications/{$application->id}", ['amount' => 1])->assertConflict(); // 409
        $this->deleteJson("/api/loan-applications/{$application->id}")->assertConflict();
    }

    public function test_draft_is_soft_deleted(): void
    {
        $draft = LoanApplication::factory()->for($this->user)->create();

        $this->deleteJson("/api/loan-applications/{$draft->id}")->assertNoContent(); // 204

        // Строка осталась в таблице, но с заполненным deleted_at...
        $this->assertSoftDeleted($draft);

        // ...и через API её больше не видно: route model binding не находит удалённые → 404
        $this->getJson("/api/loan-applications/{$draft->id}")->assertNotFound();
    }

    public function test_stats_are_counted_and_cache_is_reset_on_change(): void
    {
        LoanApplication::factory()->count(2)->for($this->user)->create();
        LoanApplication::factory()->for($this->user)->rejected()->create();

        $this->getJson('/api/loan-applications/stats')
            ->assertOk()
            ->assertExactJson(['data' => ['draft' => 2, 'submitted' => 0, 'approved' => 0, 'rejected' => 1]]);

        // Создаём ещё один черновик — событие saved в модели должно сбросить кеш
        $this->postJson('/api/loan-applications', [])->assertCreated();

        $this->getJson('/api/loan-applications/stats')->assertJsonPath('data.draft', 3);
    }
}
