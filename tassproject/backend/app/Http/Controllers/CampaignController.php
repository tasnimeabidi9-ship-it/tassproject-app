<?php

namespace App\Http\Controllers;

use App\Models\AiActionsLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Services\JobServiceClient;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        return Campaign::with('sequence:id,name')->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sequence_id' => 'required|exists:sequences,id',
        ]);

        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        return response()->json(Campaign::create($data), 201);
    }

    /**
     * POST /api/campaigns/{campaign}/start
     * Body: { contact_ids?: number[] }  — defaults to all contacts
     * currently at stage='approved' if not provided.
     *
     * Renders each sequence step's template for each contact, creates a
     * 'scheduled' Message row, and asks the job service to fire it at the
     * right time.
     */
    public function start(Request $request, Campaign $campaign, JobServiceClient $jobService)
    {
        if ($campaign->status === 'running') {
            return response()->json(['message' => 'Campaign is already running.'], 422);
        }

        $data = $request->validate([
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'exists:contacts,id',
        ]);

        $campaign->load('sequence');
        if (!$campaign->sequence) {
            return response()->json(['message' => 'Campaign has no sequence assigned.'], 422);
        }

        $contacts = isset($data['contact_ids'])
            ? Contact::whereIn('id', $data['contact_ids'])->get()
            : Contact::where('stage', 'approved')->get();

        if ($contacts->isEmpty()) {
            return response()->json(['message' => 'No eligible contacts to enroll (need stage = approved, or pass contact_ids).'], 422);
        }

        $steps = $campaign->sequence->steps;
        $scheduledCount = 0;
        $now = now();

        foreach ($contacts as $contact) {
            $target = $contact->email;
            foreach ($steps as $step) {
                if ($step['channel'] === 'sms') {
                    $target = $contact->phone;
                }
                if (!$target) {
                    continue; // can't send email without email, sms without phone
                }

                $body = $this->renderTemplate($step['body'], $contact);
                $subject = isset($step['subject']) ? $this->renderTemplate($step['subject'], $contact) : null;
                $scheduledAt = $now->copy()->addDays((int) $step['delay_days']);

                $message = Message::create([
                    'contact_id' => $contact->id,
                    'campaign_id' => $campaign->id,
                    'channel' => $step['channel'],
                    'direction' => 'out',
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'scheduled',
                    'sent_at' => null,
                ]);

                AiActionsLog::create([
                    'contact_id' => $contact->id,
                    'message_id' => $message->id,
                    'action_type' => 'send_' . $step['channel'],
                    'channel' => $step['channel'],
                    'content_preview' => mb_substr($body, 0, 200),
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt,
                ]);

                $delayMs = max(0, $now->diffInMilliseconds($scheduledAt));
                $recipientTarget = $step['channel'] === 'sms' ? $contact->phone : $contact->email;

                $jobService->scheduleMessage(
                    $message->id,
                    $step['channel'],
                    $recipientTarget,
                    $subject,
                    $body,
                    $delayMs,
                    [
                        'contact_name' => $contact->name,
                        'company' => $contact->company,
                        'template_subject' => $step['subject'] ?? null,
                        'template_body' => $step['body'],
                    ]
                );

                $scheduledCount++;
            }

            if ($contact->stage === 'approved') {
                $contact->update(['stage' => 'contacted']);
            }
        }

        $campaign->update(['status' => 'running', 'started_at' => $now]);

        return response()->json([
            'message' => "Campaign started. {$scheduledCount} message(s) scheduled across " . $contacts->count() . ' contact(s).',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * PATCH /api/campaigns/{campaign}/pause
     * Cancels every still-scheduled (not yet sent) message for this
     * campaign via the job service, then marks the campaign paused.
     */
    public function pause(Campaign $campaign, JobServiceClient $jobService)
    {
        $scheduled = Message::where('campaign_id', $campaign->id)
            ->where('status', 'scheduled')
            ->get();

        foreach ($scheduled as $message) {
            $jobService->cancelMessage($message->id);
            $message->update(['status' => 'cancelled']);
            AiActionsLog::where('message_id', $message->id)->update(['status' => 'cancelled']);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json([
            'message' => "Campaign paused. {$scheduled->count()} scheduled message(s) cancelled.",
            'campaign' => $campaign->fresh(),
        ]);
    }

    private function renderTemplate(string $template, Contact $contact): string
    {
        // Simple placeholder merge. Phase 6 replaces this with real LLM
        // personalization — this keeps the pipeline honest and functional
        // in the meantime rather than faking "AI-written" copy.
        return str_replace(
            ['{{name}}', '{{company}}', '{{email}}'],
            [$contact->name ?? 'there', $contact->company ?? 'your company', $contact->email ?? ''],
            $template
        );
    }
}
