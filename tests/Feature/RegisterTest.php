<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_get_an_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Teste Automatizado',
            'email' => 'novo.teste@example.com',
            'password' => 'SenhaTeste123!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'novo.teste@example.com',
        ]);

        $user = User::where('email', 'novo.teste@example.com')->first();

        $this->assertNotNull($user);
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'balance_cents' => 0,
        ]);
    }
}
