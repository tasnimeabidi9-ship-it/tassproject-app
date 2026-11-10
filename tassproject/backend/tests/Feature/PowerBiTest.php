<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PowerBiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_unconfigured_without_credentials(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/powerbi/config')
            ->assertOk()
            ->assertJsonPath('reports.campaign.configured', false)
            ->assertJsonPath('reports.leadfinder.configured', false)
            ->assertJsonPath('reports.pipeline.configured', false);
    }

    public function test_reports_are_configured_with_full_credentials_and_report_ids(): void
    {
        config([
            'services.powerbi' => [
                'workspace_id' => 'workspace-123',
                'tenant_id' => 'tenant-123',
                'client_id' => 'client-123',
                'client_secret' => 'secret-123',
                'report_id_campaign' => 'report-campaign',
                'report_id_leadfinder' => '',
                'report_id_pipeline' => 'report-pipeline',
            ],
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/powerbi/config')
            ->assertOk()
            ->assertJsonPath('reports.campaign.configured', true)
            ->assertJsonPath('reports.campaign.id', 'report-campaign')
            ->assertJsonPath(
                'reports.campaign.external_url',
                'https://app.powerbi.com/groups/workspace-123/reports/report-campaign'
            )
            ->assertJsonPath('reports.leadfinder.configured', false)
            ->assertJsonPath('reports.pipeline.configured', true);
    }

    public function test_embed_token_rejects_unconfigured_report(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/powerbi/embed-token?report=campaign')
            ->assertStatus(422)
            ->assertJsonPath('message', "Report 'campaign' is not configured.");
    }

    public function test_embed_token_rejects_unknown_report(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/powerbi/embed-token?report=nope')
            ->assertStatus(422)
            ->assertJsonPath('message', "Unknown report 'nope'.");
    }
}
