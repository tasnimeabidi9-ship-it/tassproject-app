<?php

namespace App\Http\Controllers;

use App\Models\AiActionsLog;
use App\Services\JobServiceClient;
use Illuminate\Http\Request;

class AiActionsLogController extends Controller
{
    /**
     * GET /api/ai/actions-log
     * Filters: status, channel, contact_id, campaign_id, date_from, date_to
     */
    public function index(Request $request)
    {
        $query = AiActionsLog::with([
            'contact:id,name,email',
            'message:id,campaign_id,channel',
            'message.campaign:id,name',
        ])->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }
        if ($contactId = $request->query('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($campaignId = $request->query('campaign_id')) {
            $query->whereHas('message', fn ($q) => $q->where('campaign_id', $campaignId));
        }
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->paginate(30);
    }

    /**
     * DELETE /api/ai/actions/{action}/cancel
     * Cancels a still-scheduled action before it fires.
     */
    public function cancel(AiActionsLog $action, JobServiceClient $jobService)
    {
        if ($action->status !== 'scheduled') {
            return response()->json(['message' => "Action is already '{$action->status}', cannot cancel."], 422);
        }

        if (!$action->message_id) {
            return response()->json(['message' => 'This action has no associated message to cancel.'], 422);
        }

        $jobService->cancelMessage($action->message_id);

        $action->update(['status' => 'cancelled']);
        $action->message()->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Action cancelled.', 'action' => $action->fresh()]);
    }
}
