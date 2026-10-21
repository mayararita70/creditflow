<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_debit_their_account(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'debito-teste-001')
            ->postJson('/api/transactions/debit', [
                'amount' => 25.00,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);
    }

    public function test_user_cannot_debit_more_than_the_available_balance(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'debito-sem-saldo-001')
            ->postJson('/api/transactions/debit', [
                'amount' => 150.00,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);
    }
    
    public function test_reusing_debit_key_with_different_amount_is_rejected(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'debito-diferente-001')
            ->postJson('/api/transactions/debit', [
                'amount' => 25.00,
            ])
            ->assertOk();

        $this->withHeader('Idempotency-Key', 'debito-diferente-001')
            ->postJson('/api/transactions/debit', [
                'amount' => 40.00,
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

}
