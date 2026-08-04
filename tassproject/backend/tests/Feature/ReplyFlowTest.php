<?php

namespace Tests\Feature;

use App\Models\AiActionsLog;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Reply;
use App\Models\Task;
use App\Models\User;
use App\Services\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReplyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulate_requires_outbound_message(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);
        $contact = Contact::create(['name' => 'Acme', 'email' => 'owner@acme.test']);

        $this->postJson('/api/replies/simulate', [
            'contact_id' => $contact->id,
            'body' => 'Hello there',
        ])->assertStatus(422);
    }

    public function test_positive_reply_creates_reply_moves_stage_and_creates_ai_task(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);

        $contact = Contact::create([
            'name' => 'Acme',
            'email' => 'owner@acme.test',
            'stage' => 'contacted',
            'assigned_to' => $rep->id,
        ]);

        $message = Message::create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'direction' => 'out',
            'subject' => 'Outreach',
            'body' => 'Hi there',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->mock(LlmClient::class)
            ->shouldReceive('classifySentiment')
            ->once()
            ->andReturn('Positive');

        $this->postJson('/api/replies/simulate', [
            'contact_id' => $contact->id,
            'body' => 'This sounds great, tell me more!',
        ])->assertCreated()
            ->assertJsonPath('sentiment', 'Positive')
            ->assertJsonPath('contact_id', $contact->id);

        $this->assertDatabaseHas('replies', [
            'contact_id' => $contact->id,
            'message_id' => $message->id,
            'sentiment' => 'Positive',
        ]);
        $this->assertSame('interested', $contact->fresh()->stage);
        $this->assertDatabaseHas('tasks', [
            'contact_id' => $contact->id,
            'created_by_ai' => true,
        ]);
        $this->assertDatabaseHas('ai_actions_log', [
            'contact_id' => $contact->id,
            'message_id' => $message->id,
            'action_type' => 'classify_reply',
        ]);
    }

    public function test_negative_reply_does_not_move_stage_or_create_task(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);

        $contact = Contact::create([
            'name' => 'Acme',
            'email' => 'owner@acme.test',
            'stage' => 'contacted',
        ]);

        Message::create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'direction' => 'out',
            'subject' => 'Outreach',
            'body' => 'Hi there',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->mock(LlmClient::class)
            ->shouldReceive('classifySentiment')
            ->once()
            ->andReturn('Negative');

        $this->postJson('/api/replies/simulate', [
            'contact_id' => $contact->id,
            'body' => 'Not interested, thanks.',
        ])->assertCreated();

        $this->assertSame('contacted', $contact->fresh()->stage);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_reply_index_returns_replies(): void
    {
        $rep = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($rep);
        $contact = Contact::create(['name' => 'Acme', 'email' => 'owner@acme.test']);
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'direction' => 'out',
            'subject' => 'Outreach',
            'body' => 'Hi',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Reply::create([
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'body' => 'Hi back',
            'sentiment' => 'Positive',
            'read_by_admin' => false,
        ]);

        $this->getJson('/api/replies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sentiment', 'Positive');
    }
}
