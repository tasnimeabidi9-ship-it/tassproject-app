<?php

namespace App\Http\Controllers;

use App\Models\AiActionsLog;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Reply;
use App\Services\LlmClient;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    /**
     * GET /api/replies
     * Query params: unread_only, contact_id
     */
    public function index(Request $request)
    {
        $query = Reply::with('contact:id,name,email,company', 'message:id,channel,subject')
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unread_only')) {
            $query->where('read_by_admin', false);
        }
        if ($contactId = $request->query('contact_id')) {
            $query->where('contact_id', $contactId);
        }

        return $query->paginate(25);
    }

    /**
     * PATCH /api/replies/{reply}/read
     */
    public function markRead(Reply $reply)
    {
        $reply->update(['read_by_admin' => true]);
        return response()->json($reply);
    }

    /**
     * POST /api/replies/simulate
     * Body: { contact_id, body }
     *
     * DEV/DEMO CONVENIENCE: a real SendGrid inbound-parse webhook needs a
     * public URL (e.g. via ngrok) to actually receive emails, which isn't
     * practical on a local dev machine. This endpoint lets you simulate
     * "a reply just arrived" against the most recent outbound message for
     * a contact, running the exact same classify+store+stage-update logic
     * a real webhook would trigger — see storeReply() below, which both
     * this and the (documented but unwired) real webhook route would call.
     */
    public function simulate(Request $request, LlmClient $llm)
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'body' => 'required|string',
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        $lastOutbound = Message::where('contact_id', $contact->id)
            ->where('direction', 'out')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastOutbound) {
            return response()->json([
                'message' => 'This contact has no outbound message yet — start a campaign for them first.',
            ], 422);
        }

        $reply = $this->storeReply($lastOutbound, $contact, $data['body'], $llm);

        return response()->json($reply, 201);
    }

    /**
     * POST /api/replies/{reply}/respond
     * Body: { body } — admin/rep sends a manual reply. Creates an
     * outbound Message (channel matches the original) and marks the
     * reply as read.
     */
    public function respond(Request $request, Reply $reply)
    {
        $data = $request->validate(['body' => 'required|string']);

        $reply->load('message');

        Message::create([
            'contact_id' => $reply->contact_id,
            'campaign_id' => $reply->message->campaign_id ?? null,
            'channel' => $reply->message->channel ?? 'email',
            'direction' => 'out',
            'subject' => null,
            'body' => $data['body'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $reply->update(['read_by_admin' => true]);

        return response()->json(['message' => 'Reply sent.']);
    }

    /**
     * Shared by simulate() above and would also be called by a real
     * SendGrid inbound-parse webhook route once you have a public URL to
     * receive it on (see README for how to add that with ngrok).
     */
    public function storeReply(Message $message, Contact $contact, string $body, LlmClient $llm): Reply
    {
        $sentiment = $llm->classifySentiment($body);

        $reply = Reply::create([
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'body' => $body,
            'sentiment' => $sentiment,
            'read_by_admin' => false,
        ]);

        AiActionsLog::create([
            'contact_id' => $contact->id,
            'message_id' => $message->id,
            'action_type' => 'classify_reply',
            'channel' => $message->channel,
            'content_preview' => mb_substr($sentiment . ': ' . $body, 0, 200),
            'status' => 'sent',
            'executed_at' => now(),
        ]);

        // Auto-move pipeline stage based on sentiment, per spec.
        if (in_array($sentiment, ['Positive', 'Interested'], true) && $contact->stage !== 'won') {
            $contact->update(['stage' => 'interested']);
            TaskController::autoCreateFromReply($contact, $sentiment);
        } elseif ($sentiment === 'Unsubscribe') {
            $contact->update(['status' => 'unsubscribed']);
        }
        // Negative / Out of Office: no automatic stage change — left for
        // a human to review in the inbox.

        return $reply->load('contact:id,name,email,company', 'message:id,channel,subject');
    }
}
