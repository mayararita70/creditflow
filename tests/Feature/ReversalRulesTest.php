<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReversalRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_reverse_a_credit_when_the_balance_was_already_spent(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 1000,
        ]);

        $credit = Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount_cents' => 5000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$credit->id}/reverse")
            ->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 1000,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_user_cannot_reverse_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $ownerAccount = Account::create([
            'user_id' => $owner->id,
            'balance_cents' => 7500,
        ]);

        Account::create([
            'user_id' => $intruder->id,
            'balance_cents' => 0,
        ]);

        $debit = Transaction::create([
            'account_id' => $ownerAccount->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/transactions/{$debit->id}/reverse")
            ->assertStatus(404);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $owner->id,
            'balance_cents' => 7500,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_cannot_reverse_a_reversal(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        $debit = Transaction::create([
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$debit->id}/reverse")
            ->assertOk();

        $reversal = Transaction::where('type', 'reversal')->first();

        $this->postJson("/api/transactions/{$reversal->id}/reverse")
            ->assertStatus(422);

        $this->assertDatabaseCount('transactions', 2);
    }
}