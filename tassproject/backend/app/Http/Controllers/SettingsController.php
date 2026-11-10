<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * GET /api/settings
     * Returns integration status, stored config, and the user list
     * (admin-only screen).
     */
    public function index()
    {
        return response()->json([
            'integrations' => $this->integrationStatus(),
            'config' => [
                'auto_approve_threshold' => (int) Setting::get('auto_approve_threshold', 80),
                'auto_approve_enabled' => Setting::get('auto_approve_enabled', '1') === '1',
                'max_requests_per_minute' => (int) Setting::get('max_requests_per_minute', 10),
            ],
            'users' => User::orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'last_login']),
        ]);
    }

    /**
     * PUT /api/settings
     * Body: { config: { auto_approve_threshold?, auto_approve_enabled?,
     *                  max_requests_per_minute? }, user?: { id, role? } }
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'config.auto_approve_threshold' => 'nullable|integer|min:0|max:100',
            'config.auto_approve_enabled' => 'nullable|boolean',
            'config.max_requests_per_minute' => 'nullable|integer|min:1|max:60',
            'user.id' => 'nullable|exists:users,id',
            'user.role' => 'nullable|in:admin,rep,readonly',
        ]);

        if (isset($data['config'])) {
            foreach ($data['config'] as $key => $value) {
                Setting::set($key, is_bool($value) ? ($value ? '1' : '0') : $value);
            }
        }

        if (isset($data['user']) && !empty($data['user']['id']) && !empty($data['user']['role'])) {
            $user = User::find($data['user']['id']);
            if ($user && $user->id !== $request->user()->id) {
                $user->update(['role' => $data['user']['role']]);
            }
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    /**
     * GET /api/settings/integrations
     * Lightweight status used by the Lead Finder header.
     */
    public function integrations()
    {
        return response()->json($this->integrationStatus());
    }

    private function integrationStatus(): array
    {
        return [
            'sendgrid' => [
                'configured' => !empty(env('SENDGRID_API_KEY')),
                'label' => 'Email delivery (SendGrid)',
                'detail' => empty(env('SENDGRID_API_KEY')) ? 'Mock mode — messages logged, not delivered' : 'Live delivery enabled',
            ],
            'twilio' => [
                'configured' => !empty(env('TWILIO_ACCOUNT_SID')) && !empty(env('TWILIO_AUTH_TOKEN')),
                'label' => 'SMS (Twilio)',
                'detail' => empty(env('TWILIO_ACCOUNT_SID')) ? 'Mock mode — SMS logged, not sent' : 'Live SMS enabled',
            ],
            'hunter' => [
                'configured' => !empty(env('HUNTER_API_KEY')),
                'label' => 'Email finder (Hunter.io)',
                'detail' => empty(env('HUNTER_API_KEY')) ? 'Mock mode — demo emails generated' : 'Verified email lookup enabled',
            ],
            'llm' => [
                'configured' => !empty(env('OPENAI_API_KEY')),
                'label' => 'LLM (OpenAI)',
                'detail' => empty(env('OPENAI_API_KEY')) ? 'Mock mode — template-based copy' : 'Personalised AI copy enabled',
            ],
            'powerbi' => [
                'configured' => $this->powerBiConfigured(),
                'label' => 'Power BI Embedded',
                'detail' => $this->powerBiConfigured()
                    ? 'Embedded reports enabled'
                    : 'Not configured — in-app charts shown instead',
            ],
        ];
    }

    /**
     * Power BI needs the full service-principal credentials plus at least
     * one report id before an embedded report can actually be rendered.
     */
    private function powerBiConfigured(): bool
    {
        $config = config('services.powerbi', []);

        foreach (['workspace_id', 'tenant_id', 'client_id', 'client_secret'] as $key) {
            if (empty($config[$key])) {
                return false;
            }
        }

        foreach (['report_id_campaign', 'report_id_leadfinder', 'report_id_pipeline'] as $key) {
            if (!empty($config[$key])) {
                return true;
            }
        }

        return false;
    }
}
