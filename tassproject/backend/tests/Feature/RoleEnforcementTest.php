<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/contacts')->assertStatus(401);
        $this->getJson('/api/tasks')->assertStatus(401);
        $this->getJson('/api/settings')->assertStatus(401);
    }

    public function test_readonly_user_cannot_write_contacts(): void
    {
        $user = User::factory()->create(['role' => 'readonly']);
        Sanctum::actingAs($user);

        $this->postJson('/api/contacts', ['name' => 'Blocked'])->assertStatus(403);
    }

    public function test_readonly_user_can_read_contacts(): void
    {
        $user = User::factory()->create(['role' => 'readonly']);
        Sanctum::actingAs($user);

        $this->getJson('/api/contacts')->assertOk();
    }

    public function test_readonly_user_cannot_open_admin_settings(): void
    {
        $user = User::factory()->create(['role' => 'readonly']);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings')->assertStatus(403);
    }

    public function test_rep_cannot_open_admin_settings(): void
    {
        $user = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings')->assertStatus(403);
    }

    public function test_admin_can_open_admin_settings(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings')->assertOk();
    }

    public function test_rep_can_create_a_task(): void
    {
        $user = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($user);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        $this->postJson('/api/tasks', [
            'contact_id' => $contact->id,
            'title' => 'Call owner',
        ])->assertCreated();
    }

    public function test_readonly_user_cannot_create_a_task(): void
    {
        $user = User::factory()->create(['role' => 'readonly']);
        Sanctum::actingAs($user);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        $this->postJson('/api/tasks', [
            'contact_id' => $contact->id,
            'title' => 'Call owner',
        ])->assertStatus(403);
    }

    public function test_everyone_authenticated_can_read_integration_status(): void
    {
        $user = User::factory()->create(['role' => 'readonly']);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings/integrations')->assertOk();
    }
}
