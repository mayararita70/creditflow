<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'name' => 'Usuário Teste',
            'email' => 'login@example.com',
            'password' => Hash::make('SenhaTeste123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'SenhaTeste123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
            ]);
    }
}
