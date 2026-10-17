<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleConnection;
use App\Services\GoogleSheets\GoogleClientFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleClientFactory $clientFactory) {}

    /**
     * Redirect admin to Google OAuth consent screen.
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        if ($request->has('return_to')) {
            $request->session()->put('google_oauth_return_to', $request->input('return_to'));
        }

        try {
            $authUrl = $this->clientFactory->createAuthUrl($state);
            return redirect()->away($authUrl);
        } catch (\Throwable $e) {
            Log::error('Failed to initiate Google OAuth: ' . $e->getMessage());
            return back()->with('error', 'Google OAuth is not configured properly: ' . $e->getMessage());
        }
    }

    /**
     * Handle the OAuth redirect callback from Google.
     */
    public function callback(Request $request)
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $returnTo      = $request->session()->pull('google_oauth_return_to');

        if (!$expectedState || $expectedState !== $request->input('state')) {
            return redirect()->route('admin.dashboard')->with('error', 'Invalid Google OAuth state. Please try again.');
        }

        if ($request->has('error')) {
            return redirect()->route('admin.dashboard')->with('error', 'Google authorization error: ' . $request->input('error'));
        }

        $code = (string) $request->input('code');
        if (empty($code)) {
            return redirect()->route('admin.dashboard')->with('error', 'Missing authorization code from Google.');
        }

        try {
            $data = $this->clientFactory->exchangeCode($code);
            $admin = Auth::guard('admin')->user();

            if (!$admin) {
                return redirect()->route('admin.login')->with('error', 'Session expired. Please log in.');
            }

            // Find existing connection for this admin or create new one
            $connection = GoogleConnection::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'google_user_id'   => $data['google_user_id'],
                    'email'            => $data['email'],
                    'avatar_url'       => $data['avatar_url'],
                    'access_token'     => $data['access_token'],
                    'refresh_token'    => $data['refresh_token'],
                    'token_expires_at' => now()->addSeconds($data['expires_in']),
                    'scopes'           => $data['scopes'],
                    'revoked_at'       => null,
                ]
            );

            $targetRoute = $returnTo ? redirect($returnTo) : redirect()->route('admin.dashboard');
            return $targetRoute->with('success', "Connected to Google successfully as {$connection->email}.");
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback error: ' . $e->getMessage());
            return redirect()->route('admin.dashboard')->with('error', 'Failed to connect Google account: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect/revoke Google account connection.
     */
    public function disconnect(Request $request, GoogleConnection $conn)
    {
        $admin = Auth::guard('admin')->user();
        if ($conn->admin_id !== $admin->id) {
            abort(403, 'Unauthorized.');
        }

        $this->clientFactory->revokeToken($conn);
        $conn->delete();

        return back()->with('success', 'Google account disconnected.');
    }

    /**
     * Retrieve an active short-lived OAuth token and credentials for the frontend Google Picker dialog.
     */
    public function pickerToken(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $conn = GoogleConnection::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            return response()->json(['error' => 'No active Google connection found.'], 404);
        }

        try {
            // This verifies the token and refreshes it if expired
            $client = $this->clientFactory->createForConnection($conn);
            $tokenData = $client->getAccessToken();

            return response()->json([
                'access_token' => $tokenData['access_token'] ?? $conn->access_token,
                'api_key'      => config('services.google.picker_api_key'),
                'app_id'       => config('services.google.app_id'),
                'email'        => $conn->email,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to obtain Picker token: ' . $e->getMessage()], 500);
        }
    }
}
