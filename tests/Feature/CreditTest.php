<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_credit_their_account(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'credito-teste-001')
            ->postJson('/api/transactions/credit', [
                'amount' => 50.00,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 5000,
        ]);
    }

    public function test_repeating_credit_with_same_key_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 0,
        ]);

        $payload = ['amount' => 50.00];

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'credito-repetido-001')
            ->postJson('/api/transactions/credit', $payload)
            ->assertOk();

        $this->withHeader('Idempotency-Key', 'credito-repetido-001')
            ->postJson('/api/transactions/credit', $payload)
            ->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 5000,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reusing_credit_key_with_different_amount_is_rejected(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'credito-diferente-001')
            ->postJson('/api/transactions/credit', [
                'amount' => 50.00,
            ])
            ->assertOk();

        $this->withHeader('Idempotency-Key', 'credito-diferente-001')
            ->postJson('/api/transactions/credit', [
                'amount' => 80.00,
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 5000,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reusing_a_debit_key_for_credit_is_rejected(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'chave-compartilhada-001')
            ->postJson('/api/transactions/debit', ['amount' => 25.00])
            ->assertOk();

        $this->withHeader('Idempotency-Key', 'chave-compartilhada-001')
            ->postJson('/api/transactions/credit', ['amount' => 25.00])
            ->assertStatus(409);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);
    }
}
