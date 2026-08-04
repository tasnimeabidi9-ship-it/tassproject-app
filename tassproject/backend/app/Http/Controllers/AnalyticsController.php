<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\LeadQueue;
use App\Models\Message;
use App\Models\Reply;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/analytics/dashboard
     * Live KPI summary for the dashboard home screen.
     *
     * HONEST NOTE: open_rate is not reported because nothing in this
     * project tracks email opens yet (that needs SendGrid's open-tracking
     * webhook, which — like inbound replies — requires a public URL).
     * reply_rate IS real, computed from actual messages/replies data.
     */
    public function dashboard()
    {
        $today = now()->startOfDay();

        $leadsFoundToday = LeadQueue::where('found_at', '>=', $today)->count();
        $emailsSentToday = Message::where('channel', 'email')->where('status', 'sent')->where('sent_at', '>=', $today)->count();
        $smsSentToday = Message::where('channel', 'sms')->where('status', 'sent')->where('sent_at', '>=', $today)->count();

        $totalSent = Message::where('status', 'sent')->count();
        $totalReplies = Reply::count();
        $replyRate = $totalSent > 0 ? round(($totalReplies / $totalSent) * 100, 1) : 0;

        $activeSequences = Campaign::where('status', 'running')->count();

        $failedMessages = Message::where('status', 'failed')->count();
        $leadsWithoutEmail = LeadQueue::whereNull('email')->count();
        $unreadReplies = Reply::where('read_by_admin', false)->count();

        $activeCampaigns = Campaign::where('status', 'running')
            ->withCount([
                'messages as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'messages as scheduled_count' => fn ($q) => $q->where('status', 'scheduled'),
            ])
            ->get(['id', 'name', 'status', 'started_at']);

        return response()->json([
            'leads_found_today' => $leadsFoundToday,
            'emails_sent_today' => $emailsSentToday,
            'sms_sent_today' => $smsSentToday,
            'reply_rate_percent' => $replyRate,
            'active_sequences' => $activeSequences,
            'alerts' => [
                'failed_messages' => $failedMessages,
                'leads_without_email' => $leadsWithoutEmail,
                'unread_replies' => $unreadReplies,
            ],
            'active_campaigns' => $activeCampaigns,
        ]);
    }

    /**
     * GET /api/analytics/overview
     * Aggregated, dashboard-wide data for the in-app analytics charts:
     * KPIs, pipeline funnel, channel/status/sentiment breakdowns,
     * a 14-day activity timeline, top companies and campaign comparison.
     */
    public function overview()
    {
        $today = now()->startOfDay();
        $since = now()->subDays(13)->startOfDay();

        $totalLeads = LeadQueue::count();
        $leadsToday = LeadQueue::where('found_at', '>=', $today)->count();
        $totalContacts = Contact::count();

        $emailsSent = Message::where('channel', 'email')->where('status', 'sent')->count();
        $smsSent = Message::where('channel', 'sms')->where('status', 'sent')->count();
        $totalSent = Message::where('status', 'sent')->count();
        $totalReplies = Reply::count();
        $replyRate = $totalSent > 0 ? round(($totalReplies / $totalSent) * 100, 1) : 0;

        $activeCampaigns = Campaign::where('status', 'running')->count();
        $wonDeals = Contact::where('stage', 'won')->count();
        $conversionRate = $totalContacts > 0 ? round(($wonDeals / $totalContacts) * 100, 1) : 0;

        // Pipeline funnel — contacts grouped by stage in pipeline order.
        $stageCounts = Contact::select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage');
        $pipeline = collect(Contact::STAGES)->map(fn ($stage) => [
            'stage' => $stage,
            'count' => (int) ($stageCounts[$stage] ?? 0),
        ])->values();

        // Channel split — sent vs replied per channel.
        $channelSplit = [
            'email' => [
                'sent' => $emailsSent,
                'replied' => Reply::whereHas('message', fn ($q) => $q->where('channel', 'email'))->count(),
            ],
            'sms' => [
                'sent' => $smsSent,
                'replied' => Reply::whereHas('message', fn ($q) => $q->where('channel', 'sms'))->count(),
            ],
        ];

        // Message status breakdown.
        $statusBreakdown = Message::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Reply sentiment breakdown.
        $sentimentCounts = Reply::select('sentiment', DB::raw('count(*) as count'))
            ->whereNotNull('sentiment')
            ->groupBy('sentiment')
            ->pluck('count', 'sentiment');

        // 14-day timeline: sent / opened / replied.
        $sentByDay = Message::where('status', 'sent')
            ->whereNotNull('sent_at')->where('sent_at', '>=', $since)
            ->select(DB::raw('DATE(sent_at) as day'), DB::raw('count(*) as count'))
            ->groupBy('day')
            ->pluck('count', 'day');
        $openedByDay = Message::whereNotNull('opened_at')->where('opened_at', '>=', $since)
            ->select(DB::raw('DATE(opened_at) as day'), DB::raw('count(*) as count'))
            ->groupBy('day')
            ->pluck('count', 'day');
        $repliedByDay = Reply::where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as count'))
            ->groupBy('day')
            ->pluck('count', 'day');

        $timeline = collect(range(13, 0))->map(function ($i) use ($sentByDay, $openedByDay, $repliedByDay) {
            $day = now()->subDays($i)->toDateString();
            return [
                'date' => $day,
                'sent' => (int) ($sentByDay[$day] ?? 0),
                'opened' => (int) ($openedByDay[$day] ?? 0),
                'replied' => (int) ($repliedByDay[$day] ?? 0),
            ];
        })->values();

        // Top companies by number of contacts.
        $topCompanies = Contact::whereNotNull('company')
            ->select('company', DB::raw('count(*) as count'))
            ->groupBy('company')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Best send time — hour and weekday with the most opens. The SQL
        // for extracting hour/day differs per database driver, so pick the
        // right expression (mysql/pgsql/sqlite) so the endpoint also works
        // on the in-memory SQLite used by phpunit.
        $bestHour = Message::whereNotNull('opened_at')
            ->select(DB::raw($this->hourExpr() . ' as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();
        $bestDay = Message::whereNotNull('opened_at')
            ->select(DB::raw($this->dayExpr() . ' as day'), DB::raw('count(*) as count'))
            ->groupBy('day')
            ->orderByDesc('count')
            ->first();
        $bestSendTime = [
            'hour' => $bestHour ? (int) $bestHour->hour : null,
            'day' => $bestDay ? $bestDay->day : null,
        ];

        // Campaign comparison.
        $campaigns = Campaign::withCount([
            'messages as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'messages as replied_count' => fn ($q) => $q->whereHas('replies'),
        ])->get(['id', 'name', 'status'])->map(function ($campaign) {
            $sent = $campaign->sent_count;
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'sent' => $sent,
                'replied' => $campaign->replied_count,
                'reply_rate_percent' => $sent > 0 ? round(($campaign->replied_count / $sent) * 100, 1) : 0,
            ];
        })->values();

        return response()->json([
            'kpis' => [
                'total_leads' => $totalLeads,
                'leads_found_today' => $leadsToday,
                'emails_sent' => $emailsSent,
                'sms_sent' => $smsSent,
                'replies_count' => $totalReplies,
                'reply_rate_percent' => $replyRate,
                'active_campaigns' => $activeCampaigns,
                'won_deals' => $wonDeals,
                'conversion_rate_percent' => $conversionRate,
            ],
            'pipeline' => $pipeline,
            'channel_split' => $channelSplit,
            'status_breakdown' => $statusBreakdown,
            'sentiment' => $sentimentCounts,
            'timeline' => $timeline,
            'top_companies' => $topCompanies,
            'campaigns' => $campaigns,
            'best_send_time' => $bestSendTime,
        ]);
    }

    /**
     * GET /api/analytics/campaign/{campaign}
     * Detailed stats for one campaign.
     */
    public function campaign(Campaign $campaign)
    {
        $messages = Message::where('campaign_id', $campaign->id);

        $totalMessages = (clone $messages)->count();
        $sent = (clone $messages)->where('status', 'sent')->count();
        $failed = (clone $messages)->where('status', 'failed')->count();
        $scheduled = (clone $messages)->where('status', 'scheduled')->count();
        $cancelled = (clone $messages)->where('status', 'cancelled')->count();

        $repliesCount = Reply::whereIn('message_id', (clone $messages)->pluck('id'))->count();
        $replyRate = $sent > 0 ? round(($repliesCount / $sent) * 100, 1) : 0;

        $contactIds = (clone $messages)->distinct()->pluck('contact_id');
        $stageBreakdown = Contact::whereIn('id', $contactIds)
            ->select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage');

        return response()->json([
            'campaign' => $campaign,
            'contacts_enrolled' => $contactIds->count(),
            'messages' => [
                'total' => $totalMessages,
                'sent' => $sent,
                'failed' => $failed,
                'scheduled' => $scheduled,
                'cancelled' => $cancelled,
            ],
            'replies_count' => $repliesCount,
            'reply_rate_percent' => $replyRate,
            'stage_breakdown' => $stageBreakdown,
        ]);
    }

    /**
     * DB-portable SQL to extract the open hour (0-23).
     */
    private function hourExpr(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => 'HOUR(opened_at)',
            'pgsql' => "EXTRACT(HOUR FROM opened_at)::int",
            default => "CAST(strftime('%H', opened_at) AS INTEGER)",
        };
    }

    /**
     * DB-portable SQL to extract the weekday name, matching MySQL's
     * DAYNAME() output (e.g. "Monday").
     */
    private function dayExpr(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => 'DAYNAME(opened_at)',
            'pgsql' => "TO_CHAR(opened_at, 'FMDay')",
            default => "CASE strftime('%w', opened_at)"
                . " WHEN '0' THEN 'Sunday' WHEN '1' THEN 'Monday'"
                . " WHEN '2' THEN 'Tuesday' WHEN '3' THEN 'Wednesday'"
                . " WHEN '4' THEN 'Thursday' WHEN '5' THEN 'Friday'"
                . " ELSE 'Saturday' END",
        };
    }
}
