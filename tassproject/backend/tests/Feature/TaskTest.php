<?php

namespace Tests\Feature;

use App\Http\Controllers\TaskController;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_can_create_a_task(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        $response = $this->postJson('/api/tasks', [
            'contact_id' => $contact->id,
            'title' => 'Follow up on quote',
            'description' => 'They asked about pricing.',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'todo')
            ->assertJsonPath('created_by_ai', false)
            ->assertJsonPath('assigned_to.id', $rep->id);
        $this->assertDatabaseHas('tasks', [
            'contact_id' => $contact->id,
            'title' => 'Follow up on quote',
        ]);
    }

    public function test_creating_task_requires_contact(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);

        $this->postJson('/api/tasks', ['title' => 'No contact'])->assertStatus(422);
    }

    public function test_rep_only_sees_own_tasks(): void
    {
        $repA = User::factory()->create(['role' => 'rep']);
        $repB = User::factory()->create(['role' => 'rep']);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $repA->id,
            'title' => "Rep A's task",
            'status' => 'todo',
            'created_by_ai' => false,
        ]);
        Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $repB->id,
            'title' => "Rep B's task",
            'status' => 'todo',
            'created_by_ai' => false,
        ]);

        Sanctum::actingAs($repA);
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', "Rep A's task");
    }

    public function test_admin_sees_all_tasks(): void
    {
        $repA = User::factory()->create(['role' => 'rep']);
        $repB = User::factory()->create(['role' => 'rep']);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $repA->id,
            'title' => "Rep A's task",
            'status' => 'todo',
            'created_by_ai' => false,
        ]);
        Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $repB->id,
            'title' => "Rep B's task",
            'status' => 'todo',
            'created_by_ai' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2);
    }

    public function test_rep_can_update_and_delete_own_task(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);
        $task = Task::create([
            'contact_id' => $contact->id,
            'assigned_to' => $rep->id,
            'title' => 'Initial',
            'status' => 'todo',
            'created_by_ai' => false,
        ]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('status', 'done');

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_auto_create_from_positive_reply_creates_ai_task(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        $contact = Contact::create([
            'name' => 'Acme Repair',
            'email' => 'owner@acme.test',
            'assigned_to' => $rep->id,
        ]);

        $task = TaskController::autoCreateFromReply($contact, 'Positive');

        $this->assertNotNull($task);
        $this->assertTrue($task->created_by_ai);
        $this->assertSame($rep->id, $task->assigned_to);
        $this->assertStringStartsWith('Follow up', $task->title);
        $this->assertNotNull($task->due_date);
    }

    public function test_auto_create_ignores_neutral_sentiment(): void
    {
        $contact = Contact::create(['name' => 'Acme Repair', 'email' => 'owner@acme.test']);

        $this->assertNull(TaskController::autoCreateFromReply($contact, 'Negative'));
        $this->assertDatabaseCount('tasks', 0);
    }
}
