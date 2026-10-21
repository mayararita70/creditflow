<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvalidAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_amounts_are_rejected_on_credit_and_debit(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $invalidAmounts = [0, -10, 'abc', 10.999];

        foreach (['credit', 'debit'] as $operation) {
            foreach ($invalidAmounts as $amount) {
                $this->actingAs($user, 'sanctum')
                    ->withHeader('Idempotency-Key', 'chave-invalida-001')
                    ->postJson("/api/transactions/{$operation}", [
                        'amount' => $amount,
                    ])
                    ->assertStatus(422);
            }
        }

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }
}