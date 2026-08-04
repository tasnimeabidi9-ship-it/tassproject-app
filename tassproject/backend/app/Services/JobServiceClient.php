<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JobServiceClient
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('JOB_SERVICE_URL', 'http://127.0.0.1:4000'), '/');
        $this->secret = env('INTERNAL_SERVICE_SECRET', '');
    }

    /**
     * Schedules one message send. $context carries the raw (unmerged)
     * template plus contact info, used by the job service's LLM step to
     * generate real personalized copy right before sending. $subject/
     * $body are the already placeholder-merged fallback, used if LLM
     * generation is skipped or fails.
     */
    public function scheduleMessage(
        int $messageId,
        string $channel,
        string $to,
        ?string $subject,
        string $body,
        int $delayMs,
        array $context = []
    ): bool {
        try {
            $response = Http::withHeaders(['X-Internal-Secret' => $this->secret])
                ->timeout(10)
                ->post("{$this->baseUrl}/schedule", array_merge([
                    'message_id' => $messageId,
                    'channel' => $channel,
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'delay_ms' => $delayMs,
                ], $context));

            if (!$response->successful()) {
                Log::warning("JobServiceClient::scheduleMessage failed for message {$messageId}: " . $response->body());
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("JobServiceClient::scheduleMessage exception for message {$messageId}: " . $e->getMessage());
            return false;
        }
    }

    public function cancelMessage(int $messageId): bool
    {
        try {
            $response = Http::withHeaders(['X-Internal-Secret' => $this->secret])
                ->timeout(10)
                ->delete("{$this->baseUrl}/schedule/{$messageId}");

            if ($response->status() === 409) {
                Log::info("JobServiceClient::cancelMessage — message {$messageId} already fired, nothing to cancel.");
                return true;
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("JobServiceClient::cancelMessage exception for message {$messageId}: " . $e->getMessage());
            return false;
        }
    }
}
