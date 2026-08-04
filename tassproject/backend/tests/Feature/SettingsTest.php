<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_integrations_endpoint_reports_all_services(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/settings/integrations')->assertOk();

        foreach (['sendgrid', 'twilio', 'hunter', 'llm', 'powerbi'] as $service) {
            $response->assertJsonPath("$service.label", fn ($v) => is_string($v) && $v !== '')
                ->assertJsonStructure([
                    $service => ['configured', 'label', 'detail'],
                ]);
        }
    }

    public function test_admin_can_read_full_settings(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonStructure([
                'integrations',
                'config' => ['auto_approve_threshold', 'auto_approve_enabled', 'max_requests_per_minute'],
                'users' => [['id', 'name', 'email', 'role', 'last_login']],
            ]);
    }

    public function test_admin_can_update_config_and_read_it_back(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/settings', [
            'config' => [
                'auto_approve_threshold' => 60,
                'auto_approve_enabled' => true,
                'max_requests_per_minute' => 20,
            ],
        ])->assertOk();

        $this->getJson('/api/settings')
            ->assertJsonPath('config.auto_approve_threshold', 60)
            ->assertJsonPath('config.auto_approve_enabled', true)
            ->assertJsonPath('config.max_requests_per_minute', 20);
    }

    public function test_admin_can_change_another_users_role(): void
    {
        Sanctum::actingAs($this->admin());
        $target = User::factory()->create(['role' => 'rep']);

        $this->putJson('/api/settings', [
            'user' => ['id' => $target->id, 'role' => 'readonly'],
        ])->assertOk();

        $this->assertSame('readonly', $target->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/settings', [
            'user' => ['id' => $admin->id, 'role' => 'readonly'],
        ])->assertOk();

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_settings_validation_rejects_bad_values(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/settings', [
            'config' => ['auto_approve_threshold' => 150],
        ])->assertStatus(422);
    }
}
