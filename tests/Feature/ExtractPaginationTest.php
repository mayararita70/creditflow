<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_history_is_paginated_with_ten_items_per_page(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'balance_cents' => 15000,
        ]);

        $ids = [];

        for ($i = 1; $i <= 15; $i++) {
            $ids[] = Transaction::create([
                'account_id' => $account->id,
                'type' => 'credit',
                'amount_cents' => 1000,
            ])->id;
        }

        $this->actingAs($user, 'sanctum');

        $firstPage = $this->getJson('/api/transactions');

        $firstPage->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 15)
            ->assertJsonPath('per_page', 10);

        $secondPage = $this->getJson('/api/transactions?page=2');

        $secondPage->assertOk()
            ->assertJsonCount(5, 'data');

        $oldestFiveIds = collect($ids)->sortDesc()->take(-5)->values()->all();

        $this->assertEquals($oldestFiveIds, $secondPage->json('data.*.id'));
    }
}