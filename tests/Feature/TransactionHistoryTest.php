<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_transaction_history(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount_cents' => 10000,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonFragment([
            'type' => 'credit',
            'amount_cents' => 10000,
        ]);

        $response->assertJsonFragment([
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);
    }

    public function test_user_cannot_view_transaction_history_without_authentication(): void
    {
        $response = $this->getJson('/api/transactions');

        $response->assertUnauthorized();
    }
    
    public function test_user_can_only_view_their_own_transaction_history(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $account1 = Account::create([
            'user_id' => $user1->id,
            'balance_cents' => 5000,
        ]);

        $account2 = Account::create([
            'user_id' => $user2->id,
            'balance_cents' => 9000,
        ]);

        Transaction::create([
            'account_id' => $account1->id,
            'type' => 'credit',
            'amount_cents' => 5000,
        ]);

        Transaction::create([
            'account_id' => $account2->id,
            'type' => 'credit',
            'amount_cents' => 9000,
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/transactions');

               $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'type' => 'credit',
                'amount_cents' => 5000,
            ]);
    }
    
    public function test_user_can_filter_transaction_history_by_type(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 7500,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount_cents' => 10000,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'type' => 'debit',
            'amount_cents' => 2500,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transactions?type=credit');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'type' => 'credit',
                'amount_cents' => 10000,
            ]);
    }

    public function test_user_can_filter_transaction_history_by_date_period(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 15000,
        ]);

        $oldTransaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount_cents' => 5000,
        ]);

        $oldTransaction->created_at = '2026-10-01 10:00:00';
        $oldTransaction->save();

        $recentTransaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount_cents' => 10000,
        ]);

        $recentTransaction->created_at = '2026-10-15 10:00:00';
        $recentTransaction->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transactions?start_date=2026-10-10&end_date=2026-10-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'amount_cents' => 10000,
            ]);
    }


}
