<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\LeadQueue;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * GET /api/leads/queue
     * Query params: approval_status (default pending_review), min_score, search, per_page
     */
    public function queue(Request $request)
    {
        $query = LeadQueue::query();

        $status = $request->query('approval_status', 'pending_review');
        if ($status !== 'all') {
            $query->where('approval_status', $status);
        }

        if ($minScore = $request->query('min_score')) {
            $query->where('match_score', '>=', (int) $minScore);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('raw_name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('match_score', 'desc')->orderBy('found_at', 'desc');

        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 25;

        return $query->paginate($perPage);
    }

    /**
     * PATCH /api/leads/{id}/approve
     * Approves the lead and creates (or merges into) a Contact record.
     */
    public function approve(Request $request, LeadQueue $lead)
    {
        $this->approveOne($lead, $request->user()->id);

        return response()->json($lead->fresh());
    }

    /**
     * PATCH /api/leads/{id}/reject
     * Body: { reason? }
     */
    public function reject(Request $request, LeadQueue $lead)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $lead->update(['approval_status' => 'rejected']);

        return response()->json($lead->fresh());
    }

    /**
     * POST /api/leads/bulk-approve
     * Body: { min_score } — approves all pending_review leads at/above this score.
     */
    public function bulkApprove(Request $request)
    {
        $data = $request->validate(['min_score' => 'required|integer|min:0|max:100']);

        $leads = LeadQueue::where('approval_status', 'pending_review')
            ->where('match_score', '>=', $data['min_score'])
            ->get();

        foreach ($leads as $lead) {
            $this->approveOne($lead, $request->user()->id);
        }

        return response()->json(['approved_count' => $leads->count()]);
    }

    private function approveOne(LeadQueue $lead, int $userId): void
    {
        $existing = null;
        if ($lead->email) {
            $existing = Contact::where('email', $lead->email)->first();
        }
        if (!$existing && $lead->domain) {
            $existing = Contact::where('domain', $lead->domain)->first();
        }

        if ($existing) {
            $existing->update([
                'match_score' => max($existing->match_score, $lead->match_score ?? 0),
                'stage' => $existing->stage === 'lead_found' ? 'approved' : $existing->stage,
            ]);
        } else {
            Contact::create([
                'name' => $lead->raw_name,
                'email' => $lead->email,
                'domain' => $lead->domain,
                'website' => $lead->domain ? "https://{$lead->domain}" : null,
                'source_url' => $lead->source_url,
                'match_score' => $lead->match_score ?? 0,
                'stage' => 'approved',
                'status' => 'active',
            ]);
        }

        $lead->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
        ]);
    }
}
