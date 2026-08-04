<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmClient
{
    public const SENTIMENTS = ['Positive', 'Interested', 'Negative', 'Out of Office', 'Unsubscribe'];

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = trim(env('OPENAI_API_KEY', ''));
        $this->model = env('OPENAI_MODEL', 'gpt-4o-mini');
    }

    public function isMock(): bool
    {
        return empty($this->apiKey);
    }

    /**
     * Classifies a reply's sentiment into one of the 5 spec-defined
     * categories. Falls back to a keyword heuristic if no OpenAI key is
     * configured — good enough to keep the pipeline demoable, not
     * intended to be production-accurate.
     */
    public function classifySentiment(string $replyBody): string
    {
        if ($this->isMock()) {
            return $this->mockClassify($replyBody);
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Classify the sentiment of a sales outreach email reply. '
                                . 'Respond with EXACTLY one of these labels and nothing else: '
                                . implode(', ', self::SENTIMENTS),
                        ],
                        ['role' => 'user', 'content' => $replyBody],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 10,
                ]);

            $label = trim($response->json('choices.0.message.content', ''));

            foreach (self::SENTIMENTS as $valid) {
                if (strcasecmp($valid, $label) === 0) {
                    return $valid;
                }
            }

            Log::warning("LlmClient::classifySentiment got unrecognized label '{$label}', falling back to heuristic.");
            return $this->mockClassify($replyBody);
        } catch (\Throwable $e) {
            Log::error('LlmClient::classifySentiment exception: ' . $e->getMessage());
            return $this->mockClassify($replyBody);
        }
    }

    private function mockClassify(string $body): string
    {
        $text = strtolower($body);

        $unsubscribeWords = ['unsubscribe', 'stop', 'remove me', 'opt out', 'do not contact'];
        foreach ($unsubscribeWords as $w) {
            if (str_contains($text, $w)) return 'Unsubscribe';
        }

        $oooWords = ['out of office', 'on vacation', 'annual leave', 'away until', 'back on'];
        foreach ($oooWords as $w) {
            if (str_contains($text, $w)) return 'Out of Office';
        }

        $negativeWords = ['not interested', 'no thanks', 'no thank you', "not a fit", 'please stop', 'not looking'];
        foreach ($negativeWords as $w) {
            if (str_contains($text, $w)) return 'Negative';
        }

        $interestedWords = ['interested', 'tell me more', 'sounds good', 'let\'s talk', 'schedule a call', 'yes please', 'sure'];
        foreach ($interestedWords as $w) {
            if (str_contains($text, $w)) return 'Interested';
        }

        return 'Positive'; // ambiguous/neutral replies default here in mock mode
    }
}
