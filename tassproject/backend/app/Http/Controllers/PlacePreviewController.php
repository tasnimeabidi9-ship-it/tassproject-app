<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlacePreviewController extends Controller
{
    /**
     * POST /api/leads/preview-search
     * Body: { query: string, location?: string }
     *
     * Real-company preview — separate from the full scraper pipeline.
     * Lets the admin type a search (e.g. "electronics repair shops in
     * Sousse") and instantly see actual existing businesses via Google
     * Places API (Text Search), before committing to a full scrape job.
     *
     * Does NOT write anything to leads_queue — this is preview-only.
     */
    public function search(Request $request)
    {
        $data = $request->validate([
            'query' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $apiKey = env('GOOGLE_PLACES_API_KEY', '');

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'Google Places API key not configured. Add GOOGLE_PLACES_API_KEY to backend/.env.',
                'results' => [],
            ], 422);
        }

        $textQuery = $data['query'];
        if (!empty($data['location'])) {
            $textQuery .= ' in ' . $data['location'];
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,'
                    . 'places.websiteUri,places.nationalPhoneNumber,places.rating,'
                    . 'places.userRatingCount,places.primaryTypeDisplayName',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery' => $textQuery,
                    'maxResultCount' => 10,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Google Places API error: ' . $response->body(),
                    'results' => [],
                ], 502);
            }

            $places = $response->json('places', []);

            $results = array_map(function ($place) {
                return [
                    'name' => $place['displayName']['text'] ?? null,
                    'address' => $place['formattedAddress'] ?? null,
                    'website' => $place['websiteUri'] ?? null,
                    'phone' => $place['nationalPhoneNumber'] ?? null,
                    'rating' => $place['rating'] ?? null,
                    'rating_count' => $place['userRatingCount'] ?? null,
                    'category' => $place['primaryTypeDisplayName']['text'] ?? null,
                ];
            }, $places);

            return response()->json(['results' => $results]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Request to Google Places API failed: ' . $e->getMessage(),
                'results' => [],
            ], 500);
        }
    }
}
