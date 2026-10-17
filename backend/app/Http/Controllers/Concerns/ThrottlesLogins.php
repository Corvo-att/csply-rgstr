<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Brute-force protection for login forms: 5 failed attempts per email+IP per minute. */
trait ThrottlesLogins
{
    protected function loginKey(Request $request, string $scope): string
    {
        return $scope.'|'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    protected function ensureNotThrottled(Request $request, string $scope): void
    {
        $key = $this->loginKey($request, $scope);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    protected function hitThrottle(Request $request, string $scope): void
    {
        RateLimiter::hit($this->loginKey($request, $scope), 60);
    }

    protected function clearThrottle(Request $request, string $scope): void
    {
        RateLimiter::clear($this->loginKey($request, $scope));
    }
}
