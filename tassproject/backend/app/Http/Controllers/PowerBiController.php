<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PowerBiController extends Controller
{
    public function embedToken(Request $request)
    {
        $reportKey = $request->query('report', 'campaign');

        $reportIds = [
            'campaign' => env('POWERBI_REPORT_ID_CAMPAIGN'),
            'leadfinder' => env('POWERBI_REPORT_ID_LEADFINDER'),
            'pipeline' => env('POWERBI_REPORT_ID_PIPELINE'),
        ];

        $reportId = $reportIds[$reportKey] ?? null;
        $workspaceId = env('POWERBI_WORKSPACE_ID');

        if (!$reportId || !$workspaceId) {
            return response()->json([
                'message' => "Report '{$reportKey}' is not configured.",
            ], 422);
        }

        $aadToken = $this->getAadToken();
        if (!$aadToken) {
            return response()->json([
                'message' => 'Could not authenticate with Azure AD.',
            ], 500);
        }

        try {
            $response = Http::withToken($aadToken)
                ->timeout(15)
                ->post("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}/GenerateToken", [
                    'accessLevel' => 'View',
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Power BI API rejected the token request.',
                ], 502);
            }

            $reportInfo = Http::withToken($aadToken)
                ->get("https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}")
                ->json();

            return response()->json([
                'embedToken' => $response->json('token'),
                'embedUrl' => $reportInfo['embedUrl'] ?? null,
                'reportId' => $reportId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Power BI embed token request failed.'], 500);
        }
    }

    public function config()
    {
        $workspaceId = env('POWERBI_WORKSPACE_ID');

        $reports = [
            'campaign' => ['label' => 'Campaign Performance', 'id' => env('POWERBI_REPORT_ID_CAMPAIGN')],
            'leadfinder' => ['label' => 'Lead Finder Report', 'id' => env('POWERBI_REPORT_ID_LEADFINDER')],
            'pipeline' => ['label' => 'Sales Pipeline Report', 'id' => env('POWERBI_REPORT_ID_PIPELINE')],
        ];

        foreach ($reports as $key => &$r) {
            $r['configured'] = !empty($r['id']) && !empty($workspaceId);
            $r['external_url'] = $r['configured']
                ? "https://app.powerbi.com/groups/{$workspaceId}/reports/{$r['id']}"
                : null;
        }

        return response()->json(['reports' => $reports]);
    }

    private function getAadToken(): ?string
    {
        return Cache::remember('powerbi_aad_token', 3000, function () {
            $tenantId = env('POWERBI_TENANT_ID');
            $clientId = env('POWERBI_CLIENT_ID');
            $clientSecret = env('POWERBI_CLIENT_SECRET');

            if (!$tenantId || !$clientId || !$clientSecret) {
                return null;
            }

            $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://analysis.windows.net/powerbi/api/.default',
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }
}
