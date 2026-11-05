<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class GoogleIdentityService
{
    /**
     * Verify a Google Identity Services ID token against Google's token endpoint.
     *
     * @return array{sub: string, email: string, name: string, picture: ?string, email_verified: bool}
     */
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) {
            throw new ServiceUnavailableHttpException(null, 'La connexion Google n’est pas configurée sur le serveur.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Google est temporairement inaccessible. Réessayez plus tard.', $exception);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages(['credential' => 'Le jeton Google est invalide ou expiré.']);
        }

        $claims = $response->json();
        if (! is_array($claims)) {
            throw ValidationException::withMessages(['credential' => 'Impossible de vérifier ce compte Google.']);
        }

        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        $issuer = $claims['iss'] ?? '';

        if (
            ! hash_equals((string) $clientId, (string) ($claims['aud'] ?? ''))
            || ! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! $expiresAt
            || $expiresAt <= time()
            || filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true
            || empty($claims['sub'])
            || empty($claims['email'])
        ) {
            throw ValidationException::withMessages(['credential' => 'Impossible de vérifier ce compte Google.']);
        }

        $picture = filter_var($claims['picture'] ?? null, FILTER_VALIDATE_URL);
        $pictureHost = $picture ? parse_url($picture, PHP_URL_HOST) : null;

        return [
            'sub' => (string) $claims['sub'],
            'email' => strtolower((string) $claims['email']),
            'name' => trim((string) ($claims['name'] ?? $claims['email'])),
            'picture' => $picture && parse_url($picture, PHP_URL_SCHEME) === 'https'
                && is_string($pictureHost) && str_ends_with($pictureHost, '.googleusercontent.com')
                    ? $picture
                    : null,
            'email_verified' => true,
        ];
    }
}
