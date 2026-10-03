<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тест консольной команды: artisan-команды в тестах вызываются так же просто, как HTTP-запросы.
 */
class PruneStaleDraftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_stale_drafts_are_deleted(): void
    {
        // updated_at можно задать явно — фабрика пишет его как обычное поле
        $staleDraft = LoanApplication::factory()->create(['updated_at' => now()->subDays(40)]);
        $freshDraft = LoanApplication::factory()->create(['updated_at' => now()->subDays(5)]);
        $oldSubmitted = LoanApplication::factory()->submitted()->create(['updated_at' => now()->subDays(40)]);

        $this->artisan('loan-applications:prune-drafts')
            ->expectsOutput('Удалено черновиков: 1')
            ->assertSuccessful();

        $this->assertSoftDeleted($staleDraft);
        $this->assertNotSoftDeleted($freshDraft);
        $this->assertNotSoftDeleted($oldSubmitted); // отправленные заявки не трогаем, даже старые
    }

    public function test_days_option_overrides_the_config(): void
    {
        $draft = LoanApplication::factory()->create(['updated_at' => now()->subDays(5)]);

        $this->artisan('loan-applications:prune-drafts', ['--days' => 3])->assertSuccessful();

        $this->assertSoftDeleted($draft);
    }
}
