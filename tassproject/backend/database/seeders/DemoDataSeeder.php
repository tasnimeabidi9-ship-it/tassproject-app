<?php

namespace Database\Seeders;

use App\Models\AiActionsLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\LeadQueue;
use App\Models\Message;
use App\Models\Reply;
use App\Models\ScrapeJob;
use App\Models\Sequence;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Contact::where('source_url', 'demo-seed')->exists()) {
            return;
        }

        $admin = User::where('role', 'admin')->first();

        $profile = TargetProfile::create([
            'name' => 'Repair shops in Tunisia',
            'industry' => 'electronics repair',
            'job_title' => 'owner',
            'location' => 'Sousse, Tunisia',
            'keywords' => ['atelier', 'réparation', 'électronique'],
            'created_by' => $admin?->id,
        ]);

        $scrapeJob = ScrapeJob::create([
            'profile_id' => $profile->id,
            'queries_run' => 4,
            'leads_found' => 6,
            'emails_enriched' => 5,
            'status' => 'completed',
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5)->addMinutes(3),
        ]);

        $leadNames = [
            ['Karim Sassi', 'karim.sassi@atelier-electronique-sousse.tn', 'atelier-electronique-sousse.tn', 82, 88, 'approved'],
            ['Nadia Ferjani', 'nadia@techfixpro.tn', 'techfixpro.tn', 74, 91, 'approved'],
            ['Mounir Gharbi', 'mounir@electroplus-sfax.tn', 'electroplus-sfax.tn', 65, 70, 'pending_review'],
            ['Yassmine Cherif', 'yassmine@reparation-rapide-bizerte.tn', 'reparation-rapide-bizerte.tn', 58, 62, 'pending_review'],
            ['Bilel Trabelsi', 'bilel@mecafix-sousse.tn', 'mecafix-sousse.tn', 45, 55, 'rejected'],
        ];

        foreach ($leadNames as [$name, $email, $domain, $confidence, $match, $status]) {
            LeadQueue::create([
                'profile_id' => $profile->id,
                'raw_name' => $name,
                'domain' => $domain,
                'email' => $email,
                'confidence_score' => $confidence,
                'match_score' => $match,
                'source_url' => 'demo-seed',
                'approval_status' => $status,
                'approved_by' => $status === 'approved' ? $admin?->id : null,
                'found_at' => now()->subDays(5),
            ]);
        }

        $sequence = Sequence::create([
            'name' => 'Standard Outreach',
            'steps' => [
                ['channel' => 'email', 'delay_days' => 0, 'subject' => 'Quick question, {{name}}', 'body' => 'Hi {{name}}, I noticed {{company}} might benefit from our platform.'],
                ['channel' => 'email', 'delay_days' => 3, 'subject' => 'Following up', 'body' => 'Hi {{name}}, just following up on my last email.'],
            ],
        ]);

        $campaign = Campaign::create([
            'name' => 'Q3 Outreach',
            'sequence_id' => $sequence->id,
            'status' => 'running',
            'started_at' => now()->subDays(4),
            'created_by' => $admin?->id,
        ]);

        $contactsData = [
            ['Amine Ben Ali', 'amine@atelier-electronique-sousse.tn', 'Atelier Electronique Sousse', 'atelier-electronique-sousse.tn', 78, 'won'],
            ['Sara Trabelsi', 'sara@techfixpro.tn', 'TechFix Pro', 'techfixpro.tn', 85, 'interested'],
            ['Rania Jendoubi', 'rania@electroplus-sfax.tn', 'Electro Plus Sfax', 'electroplus-sfax.tn', 71, 'contacted'],
            ['Khalil Zribi', 'khalil@reparation-rapide-bizerte.tn', 'Reparation Rapide Bizerte', 'reparation-rapide-bizerte.tn', 60, 'approved'],
            ['Wafa Bouazizi', 'wafa@informatique-tunis.tn', 'Informatique Tunis', 'informatique-tunis.tn', 40, 'lost'],
            ['Fares Hammami', 'fares@mecafix-sousse.tn', 'MecaFix Sousse', 'mecafix-sousse.tn', 92, 'won'],
            ['Ines Mabrouk', 'ines@telecomplus-gafsa.tn', 'Telecom Plus Gafsa', 'telecomplus-gafsa.tn', 55, 'lead_found'],
        ];

        $contacts = [];
        foreach ($contactsData as [$name, $email, $company, $domain, $score, $stage]) {
            $contacts[] = Contact::create([
                'name' => $name,
                'email' => $email,
                'phone' => '+2162000' . rand(1000, 9999),
                'company' => $company,
                'domain' => $domain,
                'website' => "https://{$domain}",
                'source_url' => 'demo-seed',
                'match_score' => $score,
                'stage' => $stage,
                'status' => 'active',
                'assigned_to' => $admin?->id,
                'created_at' => now()->subDays(rand(6, 14)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);
        }

        $now = now();
        foreach ($contacts as $i => $contact) {
            if (in_array($contact->stage, ['lead_found', 'approved'])) {
                continue;
            }

            $sentAt = $now->copy()->subDays(rand(1, 4));
            $message = Message::create([
                'contact_id' => $contact->id,
                'campaign_id' => $campaign->id,
                'channel' => 'email',
                'direction' => 'out',
                'subject' => "Quick question, {$contact->name}",
                'body' => "Hi {$contact->name}, I noticed {$contact->company} might benefit from our platform.",
                'status' => 'sent',
                'sent_at' => $sentAt,
                'created_at' => $sentAt->copy()->subMinutes(1),
                'updated_at' => $sentAt,
            ]);

            AiActionsLog::create([
                'contact_id' => $contact->id,
                'message_id' => $message->id,
                'action_type' => 'send_email',
                'channel' => 'email',
                'content_preview' => mb_substr($message->body, 0, 200),
                'status' => 'sent',
                'scheduled_at' => $sentAt->copy()->subMinutes(1),
                'executed_at' => $sentAt,
            ]);

            if ($i % 2 === 0) {
                $futureMessage = Message::create([
                    'contact_id' => $contact->id,
                    'campaign_id' => $campaign->id,
                    'channel' => 'email',
                    'direction' => 'out',
                    'subject' => 'Following up',
                    'body' => "Hi {$contact->name}, just following up on my last email.",
                    'status' => 'scheduled',
                ]);
                AiActionsLog::create([
                    'contact_id' => $contact->id,
                    'message_id' => $futureMessage->id,
                    'action_type' => 'send_email',
                    'channel' => 'email',
                    'content_preview' => mb_substr($futureMessage->body, 0, 200),
                    'status' => 'scheduled',
                    'scheduled_at' => $now->copy()->addDays(2),
                ]);
            }

            if (in_array($contact->stage, ['interested', 'won', 'contacted'])) {
                $sentiment = match ($contact->stage) {
                    'won', 'interested' => 'Interested',
                    default => 'Positive',
                };
                Reply::create([
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'body' => match ($sentiment) {
                        'Interested' => "Yes, I'm interested — tell me more!",
                        default => 'Thanks for reaching out, let me think about it.',
                    },
                    'sentiment' => $sentiment,
                    'read_by_admin' => (bool) rand(0, 1),
                    'created_at' => $sentAt->copy()->addHours(rand(2, 20)),
                ]);
            }

            if ($contact->stage === 'lost') {
                $message->update(['status' => 'failed']);
                AiActionsLog::where('message_id', $message->id)->update(['status' => 'failed']);
            }
        }
    }
}
