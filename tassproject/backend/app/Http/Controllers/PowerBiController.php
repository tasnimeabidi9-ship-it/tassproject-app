<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PowerBiController extends Controller
{
    private const REPORT_KEYS = ['campaign', 'leadfinder', 'pipeline'];

    private const REPORT_LABELS = [
        'campaign' => 'Campaign Performance',
        'leadfinder' => 'Lead Finder Report',
        'pipeline' => 'Sales Pipeline Report',
    ];

    public function embedToken(Request $request)
    {
        $reportKey = $request->query('report', 'campaign');

        if (!in_array($reportKey, self::REPORT_KEYS, true)) {
            return response()->json([
                'message' => "Unknown report '{$reportKey}'.",
            ], 422);
        }

        $report = $this->reportConfig($reportKey);

        if (!$report['configured']) {
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
            $reportInfo = Http::withToken($aadToken)
                ->timeout(15)
                ->get("https://api.powerbi.com/v1.0/myorg/groups/{$report['workspace_id']}/reports/{$report['report_id']}");

            if (!$reportInfo->successful() || !$reportInfo->json('embedUrl')) {
                return response()->json([
                    'message' => 'Could not load the Power BI report details.',
                ], 502);
            }

            $response = Http::withToken($aadToken)
                ->timeout(15)
                ->post("https://api.powerbi.com/v1.0/myorg/groups/{$report['workspace_id']}/reports/{$report['report_id']}/GenerateToken", [
                    'accessLevel' => 'View',
                ]);

            if (!$response->successful() || !$response->json('token')) {
                return response()->json([
                    'message' => 'Power BI API rejected the token request.',
                ], 502);
            }

            return response()->json([
                'embedToken' => $response->json('token'),
                'embedUrl' => $reportInfo->json('embedUrl'),
                'reportId' => $report['report_id'],
                'expiresAt' => $response->json('expiration'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Power BI embed token request failed.',
            ], 500);
        }
    }

    public function config()
    {
        $reports = [];

        foreach (self::REPORT_KEYS as $key) {
            $report = $this->reportConfig($key);

            $reports[$key] = [
                'label' => $report['label'],
                'id' => $report['report_id'] ?: null,
                'configured' => $report['configured'],
                'external_url' => $report['configured']
                    ? "https://app.powerbi.com/groups/{$report['workspace_id']}/reports/{$report['report_id']}"
                    : null,
            ];
        }

        return response()->json(['reports' => $reports]);
    }

    /**
     * Resolve one report's configuration from config/services.php.
     *
     * config() is used instead of env() so the values still resolve when
     * the application runs with a cached configuration (php artisan config:cache).
     */
    private function reportConfig(string $key): array
    {
        $config = config('services.powerbi', []);
        $reportId = $config["report_id_{$key}"] ?? '';

        return [
            'label' => self::REPORT_LABELS[$key],
            'workspace_id' => $config['workspace_id'] ?? '',
            'report_id' => $reportId,
            'configured' => $this->credentialsConfigured() && !empty($reportId),
        ];
    }

    private function credentialsConfigured(): bool
    {
        $config = config('services.powerbi', []);

        foreach (['workspace_id', 'tenant_id', 'client_id', 'client_secret'] as $key) {
            if (empty($config[$key])) {
                return false;
            }
        }

        return true;
    }

    private function getAadToken(): ?string
    {
        $cached = Cache::get('powerbi_aad_token');
        if ($cached) {
            return $cached;
        }

        $tenantId = config('services.powerbi.tenant_id');
        $clientId = config('services.powerbi.client_id');
        $clientSecret = config('services.powerbi.client_secret');

        if (!$tenantId || !$clientId || !$clientSecret) {
            return null;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://analysis.windows.net/powerbi/api/.default',
            ]);

        $token = $response->successful() ? $response->json('access_token') : null;

        // Only cache successful tokens, so a transient failure can be retried
        // on the next request instead of being remembered for 50 minutes.
        if ($token) {
            Cache::put('powerbi_aad_token', $token, 3000);
        }

        return $token;
    }
}
