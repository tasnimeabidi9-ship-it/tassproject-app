<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\LeadQueue;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create(['role' => 'rep']);
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_dashboard_returns_kpi_structure(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/analytics/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'leads_found_today',
                'emails_sent_today',
                'sms_sent_today',
                'reply_rate_percent',
                'active_sequences',
                'alerts' => ['failed_messages', 'leads_without_email', 'unread_replies'],
                'active_campaigns',
            ]);
    }

    public function test_overview_returns_full_chart_structure(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonStructure([
                'kpis' => ['total_leads', 'emails_sent', 'replies_count', 'won_deals'],
                'pipeline',
                'channel_split' => ['email' => ['sent', 'replied'], 'sms' => ['sent', 'replied']],
                'status_breakdown',
                'sentiment',
                'timeline',
                'top_companies',
                'campaigns',
                'best_send_time' => ['hour', 'day'],
            ])
            ->assertJsonCount(14, 'timeline')
            ->assertJsonCount(7, 'pipeline');
    }

    public function test_overview_counts_seeded_data(): void
    {
        $this->actingAsUser();

        LeadQueue::create(['email' => 'a@x.test', 'domain' => 'x.test', 'approval_status' => 'pending_review']);
        Contact::create(['name' => 'Acme', 'company' => 'Acme Repair', 'stage' => 'won', 'status' => 'active']);
        Contact::create(['name' => 'Beta', 'company' => 'Beta LLC', 'stage' => 'approved', 'status' => 'active']);

        $this->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonPath('kpis.total_leads', 1)
            ->assertJsonPath('kpis.won_deals', 1)
            ->assertJsonCount(2, 'top_companies')
            ->assertJsonFragment(['company' => 'Acme Repair'])
            ->assertJsonFragment(['company' => 'Beta LLC']);
    }

    public function test_best_send_time_is_populated_from_opens(): void
    {
        $this->actingAsUser();

        $contact = Contact::create(['name' => 'Acme', 'email' => 'owner@acme.test']);
        Message::create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'direction' => 'out',
            'subject' => 'Hello',
            'body' => 'Hi',
            'status' => 'sent',
            'sent_at' => now(),
            'opened_at' => now()->utc()->setTime(9, 30),
        ]);

        $this->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonPath('best_send_time.hour', 9)
            ->assertJsonPath('best_send_time.day', now()->utc()->format('l'));
    }

    public function test_best_send_time_is_null_without_opens(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonPath('best_send_time.hour', null)
            ->assertJsonPath('best_send_time.day', null);
    }
}
