<?php

namespace App\Http\Controllers;

use App\Models\ScrapeJob;
use Illuminate\Http\Request;

class ScraperController extends Controller
{
    /**
     * POST /api/scraper/start
     * Body: { profile_id }
     *
     * NOTE: This creates the job record only. The actual Python/Playwright
     * worker that picks up 'pending' jobs, runs the Google search scraping,
     * and calls Hunter.io is built in the next Phase 3 chunk. Until that
     * worker exists, jobs will stay in 'pending' status.
     */
    public function start(Request $request)
    {
        $data = $request->validate([
            'profile_id' => 'required|exists:target_profiles,id',
        ]);

        $job = ScrapeJob::create([
            'profile_id' => $data['profile_id'],
            'status' => 'pending',
            'queries_run' => 0,
            'leads_found' => 0,
            'emails_enriched' => 0,
        ]);

        return response()->json($job, 201);
    }

    /**
     * GET /api/scraper/status/{job}
     */
    public function status(ScrapeJob $job)
    {
        return response()->json($job);
    }

    /**
     * GET /api/scraper/jobs
     * Recent jobs, most recent first — used to populate the Lead Finder screen.
     */
    public function recentJobs()
    {
        return ScrapeJob::with('profile:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }
}
