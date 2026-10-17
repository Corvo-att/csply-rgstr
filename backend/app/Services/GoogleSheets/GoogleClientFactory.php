<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleConnection;
use Google\Client as GoogleClient;
use Google\Service\Drive as DriveService;
use Google\Service\Oauth2 as Oauth2Service;
use Google\Service\Sheets as SheetsService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleClientFactory
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId     = (string) config('services.google.client_id', '');
        $this->clientSecret = (string) config('services.google.client_secret', '');
        $this->redirectUri  = (string) config('services.google.redirect_uri', route('admin.google.callback'));
    }

    /**
     * Build a base Google Client configured for OAuth authentication.
     */
    public function createBaseClient(): GoogleClient
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new RuntimeException(
                'Google Client credentials are not configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env file.'
            );
        }

        $client = new GoogleClient();
        $client->setApplicationName(config('app.name', 'EGYCON Cosplayer Register'));
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // Force refresh token issuance
        $client->setIncludeGrantedScopes(true);

        // Requested scopes: Sheets, Drive (drive.file non-sensitive), and basic profile/email info
        $client->addScope([
            SheetsService::SPREADSHEETS,
            DriveService::DRIVE_FILE,
            'openid',
            'email',
            'profile',
        ]);

        return $client;
    }

    /**
     * Generate the OAuth authorization URL.
     */
    public function createAuthUrl(string $state): string
    {
        $client = $this->createBaseClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Exchange the authorization code for tokens and user profile info.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, email: string, google_user_id: ?string, avatar_url: ?string, scopes: array}
     */
    public function exchangeCode(string $code): array
    {
        $client = $this->createBaseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Failed to exchange Google OAuth code: ' . ($token['error_description'] ?? $token['error']));
        }

        $client->setAccessToken($token);

        // Fetch user info using Oauth2 service
        $oauth2 = new Oauth2Service($client);
        $userInfo = $oauth2->userinfo->get();

        $scopes = isset($token['scope']) ? explode(' ', (string) $token['scope']) : [];

        return [
            'access_token'    => (string) ($token['access_token'] ?? ''),
            'refresh_token'   => isset($token['refresh_token']) ? (string) $token['refresh_token'] : null,
            'expires_in'      => (int) ($token['expires_in'] ?? 3600),
            'email'           => (string) ($userInfo->getEmail() ?? ''),
            'google_user_id'  => (string) ($userInfo->getId() ?? ''),
            'avatar_url'      => (string) ($userInfo->getPicture() ?? ''),
            'scopes'          => $scopes,
        ];
    }

    /**
     * Create an authenticated Google Client for a given connection.
     * Automatically handles access token expiration and refresh.
     */
    public function createForConnection(GoogleConnection $connection): GoogleClient
    {
        if (!$connection->isConnected()) {
            throw new RuntimeException('The Google Connection is revoked or inactive. Please reconnect.');
        }

        $client = $this->createBaseClient();
        $client->setAccessToken([
            'access_token'  => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
            'expires_in'    => $connection->token_expires_at ? max(0, $connection->token_expires_at->diffInSeconds(now())) : 0,
            'created'       => $connection->updated_at?->timestamp ?? time(),
        ]);

        // If the access token is expired or expiring soon, refresh it
        if ($client->isAccessTokenExpired() && $connection->refresh_token) {
            try {
                $refreshed = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

                if (isset($refreshed['error'])) {
                    if (in_array($refreshed['error'], ['invalid_grant', 'unauthorized_client'])) {
                        $connection->update(['revoked_at' => now()]);
                        Log::warning("Google connection {$connection->id} has been revoked by Google: {$refreshed['error']}");
                    }
                    throw new RuntimeException('Failed to refresh Google access token: ' . ($refreshed['error_description'] ?? $refreshed['error']));
                }

                $connection->update([
                    'access_token'     => $refreshed['access_token'],
                    'refresh_token'    => $refreshed['refresh_token'] ?? $connection->refresh_token,
                    'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
                ]);

                $client->setAccessToken($refreshed);
            } catch (\Throwable $e) {
                Log::error("Error refreshing Google token for connection {$connection->id}: " . $e->getMessage());
                throw $e;
            }
        }

        return $client;
    }

    /**
     * Revoke access token and refresh token via Google OAuth endpoint.
     */
    public function revokeToken(GoogleConnection $connection): void
    {
        if ($connection->access_token || $connection->refresh_token) {
            try {
                $client = $this->createBaseClient();
                $client->revokeToken($connection->refresh_token ?: $connection->access_token);
            } catch (\Throwable $e) {
                Log::warning("Revoking Google token failed for connection {$connection->id}: " . $e->getMessage());
            }
        }

        $connection->update([
            'revoked_at'    => now(),
            'access_token'  => '',
            'refresh_token' => null,
        ]);
    }
}
