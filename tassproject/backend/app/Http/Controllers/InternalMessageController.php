<?php

namespace App\Http\Controllers;

use App\Models\AiActionsLog;
use App\Models\Message;
use Illuminate\Http\Request;

class InternalMessageController extends Controller
{
    /**
     * PATCH /api/internal/messages/{message}
     * Called by the Node job service once it has attempted to send a
     * message. Body: {
     *   status: 'sent'|'failed',
     *   provider_message_id?, error?,
     *   final_subject?, final_body?   // what the LLM actually generated
     * }
     */
    public function updateStatus(Request $request, Message $message)
    {
        $data = $request->validate([
            'status' => 'required|in:sent,failed',
            'provider_message_id' => 'nullable|string|max:255',
            'error' => 'nullable|string|max:500',
            'final_subject' => 'nullable|string|max:255',
            'final_body' => 'nullable|string',
        ]);

        $update = [
            'status' => $data['status'],
            'sent_at' => $data['status'] === 'sent' ? now() : $message->sent_at,
        ];

        if (!empty($data['final_subject'])) {
            $update['subject'] = $data['final_subject'];
        }
        if (!empty($data['final_body'])) {
            $update['body'] = $data['final_body'];
        }

        $message->update($update);

        $logUpdate = [
            'status' => $data['status'],
            'executed_at' => now(),
        ];
        if (!empty($data['final_body'])) {
            $logUpdate['content_preview'] = mb_substr($data['final_body'], 0, 200);
        }

        // Precise match via message_id (Phase 7) — no more "latest
        // scheduled for this contact+channel" guessing.
        AiActionsLog::where('message_id', $message->id)->update($logUpdate);

        return response()->json(['message' => 'Status updated.']);
    }
}
