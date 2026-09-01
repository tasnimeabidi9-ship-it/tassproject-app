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

        // Offline/demo mode: no key configured, so return realistic-looking
        // results from a local database instead of erroring. This keeps the
        // demo working even with no internet and no API key.
        if (empty($apiKey)) {
            $mock = [
                ['Atelier Electronique', 'Avenue Habib Bourguiba, Sousse', 'atelier-electronique-sousse.tn', '+216 73 224 118', 4.6, 137, 'Electronics repair shop'],
                ['TechFix Pro', "Rue de l'Independance, Tunis", 'techfixpro.tn', '+216 71 555 402', 4.4, 89, 'Computer repair service'],
                ['Electro Plus Sfax', 'Route de Gabes, Sfax', 'electroplus-sfax.tn', '+216 74 400 215', 4.2, 64, 'Electronics store'],
                ['Reparation Rapide', 'Avenue Farhat Hached, Bizerte', 'reparation-rapide-bizerte.tn', '+216 72 330 901', 4.1, 52, 'Phone repair shop'],
                ['MecaFix Sousse', 'Rue de la Republique, Sousse', 'mecafix-sousse.tn', '+216 73 500 733', 4.7, 201, 'Auto repair shop'],
                ['Informatique Tunis', 'Avenue de Carthage, Tunis', 'informatique-tunis.tn', '+216 71 300 884', 4.3, 118, 'Computer store'],
                ['Rania Services', 'Boulevard du 14 Janvier, Monastir', 'rania-services.tn', '+216 73 461 209', 4.0, 33, 'General contractor'],
                ['Telecom Plus', 'Avenue Ali Bourguiba, Gafsa', 'telecomplus-gafsa.tn', '+216 76 220 515', 3.9, 27, 'Telecommunications'],
            ];

            $needle = mb_strtolower(trim(($data['query'] ?? '') . ' ' . ($data['location'] ?? '')));
            $words = array_values(array_filter(preg_split('/\s+/', $needle)));

            $results = array_values(array_filter(array_map(function ($p) {
                return [
                    'name' => $p[0],
                    'address' => $p[1],
                    'website' => $p[2],
                    'phone' => $p[3],
                    'rating' => $p[4],
                    'rating_count' => $p[5],
                    'category' => $p[6],
                ];
            }, $mock), function ($p) use ($words) {
                if (empty($words)) {
                    return true;
                }

                $haystack = mb_strtolower($p['name'] . ' ' . $p['address'] . ' ' . $p['category']);
                foreach ($words as $word) {
                    if (mb_strpos($haystack, $word) !== false) {
                        return true;
                    }
                }

                return false;
            }));

            return response()->json([
                'message' => 'Demo preview (offline). Configure GOOGLE_PLACES_API_KEY for live results.',
                'results' => array_slice($results, 0, 8),
            ]);
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
