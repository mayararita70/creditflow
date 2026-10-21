<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reverse_a_debit_transaction(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => 'reversal',
            'amount_cents' => 2500,
            'reversal_of_id' => $transaction->id,
        ]);
                $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);
    }

    public function test_user_cannot_reverse_the_same_transaction_twice(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/reverse")
            ->assertOk();

        $this->postJson("/api/transactions/{$transaction->id}/reverse")
            ->assertStatus(409);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 10000,
        ]);

        $this->assertDatabaseCount('transactions', 2);
    }
}
