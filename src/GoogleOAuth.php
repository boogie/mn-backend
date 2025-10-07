<?php
namespace MagicianNews;

use League\OAuth2\Client\Provider\Google;

class GoogleOAuth {
    private Google $provider;

    public function __construct() {
        $this->provider = new Google([
            'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
        ]);
    }

    public function getAuthorizationUrl(): string {
        return $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);
    }

    public function getAccessToken(string $code): array {
        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            $user = $this->provider->getResourceOwner($token);

            return [
                'google_id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'avatar' => $user->getAvatar(),
            ];
        } catch (\Exception $e) {
            error_log("Google OAuth Error: " . $e->getMessage());
            throw new \Exception("Failed to authenticate with Google");
        }
    }
}
