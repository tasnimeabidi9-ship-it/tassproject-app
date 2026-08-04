<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create(['role' => 'rep']);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('role', 'rep');
    }

    public function test_guest_is_rejected_from_protected_route(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['role' => 'rep']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout')
            ->assertOk();

        // The resolved auth guard is cached per-application-instance during
        // tests, so a fresh guard must be created before the next request
        // (in production every HTTP request gets a fresh application).
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
