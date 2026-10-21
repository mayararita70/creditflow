<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_not_changed_when_saving_the_history_fails(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        Transaction::creating(function () {
            throw new RuntimeException('Falha simulada ao gravar o historico.');
        });

        try {
            $this->actingAs($user, 'sanctum')
                ->withHeader('Idempotency-Key', 'atomico-credito-001')
                ->postJson('/api/transactions/credit', ['amount' => 50.00])
                ->assertStatus(500);

            $this->withHeader('Idempotency-Key', 'atomico-debito-001')
                ->postJson('/api/transactions/debit', ['amount' => 30.00])
                ->assertStatus(500);
        } finally {
            Transaction::flushEventListeners();
        }

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }
}