<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the read-only API used by the EGYCON Voting system.
 * Send:  Authorization: Bearer <VOTING_API_TOKEN>
 */
class AuthenticateVotingApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.voting.token');

        // No token configured = API switched off (never "open by default").
        if ($expected === '') {
            return response()->json(['message' => 'Voting API is not enabled.'], 503);
        }

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
