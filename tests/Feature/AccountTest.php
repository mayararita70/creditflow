<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_account_balance(): void
    {
        $user = User::factory()->create();

        Account::create([
            'user_id' => $user->id,
            'balance_cents' => 15000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/account');

        $response->assertOk()
            ->assertJson([
                'balance_cents' => 15000,
                'balance' => '150,00',
            ]);
    }
    
    public function test_user_can_only_view_their_own_account_balance(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Account::create([
            'user_id' => $user1->id,
            'balance_cents' => 15000,
        ]);

        Account::create([
            'user_id' => $user2->id,
            'balance_cents' => 90000,
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/account');

        $response->assertOk()
            ->assertJson([
                'balance_cents' => 15000,
                'balance' => '150,00',
            ])
            ->assertJsonMissing([
                'balance_cents' => 90000,
                'balance' => '900,00',
            ]);
    }

}
