<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = env('INTERNAL_SERVICE_SECRET', '');
        $provided = $request->header('X-Internal-Secret', '');

        if (!$expected || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid internal secret.'], 401);
        }

        return $next($request);
    }
}
