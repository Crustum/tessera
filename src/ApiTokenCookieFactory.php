<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\I18n\DateTime;
use Firebase\JWT\JWT;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Creates the transient API token cookie used by first-party SPAs.
 */
class ApiTokenCookieFactory
{
    /**
     * Bind session cookie options and the JWT signing key.
     *
     * @param array<string, mixed> $session Session configuration (lifetime, path, domain, secure, same_site, partitioned)
     * @param string $encryptionKey Default JWT signing key
     */
    public function __construct(
        protected array $session,
        protected string $encryptionKey,
    ) {
    }

    /**
     * CSRF claim for the transient API cookie, or null when minting must be skipped.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return string|null
     */
    public static function csrfClaimFromRequest(ServerRequestInterface $request): ?string
    {
        $csrfToken = $request->getAttribute('csrfToken');
        if (!is_string($csrfToken) || $csrfToken === '') {
            return null;
        }

        return $csrfToken;
    }

    /**
     * Create an API token cookie for the given user and CSRF token.
     *
     * @param string|int $userId User identifier
     * @param string $csrfToken CSRF token
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function make(string|int $userId, string $csrfToken): Cookie
    {
        $expiration = DateTime::now()
            ->addMinutes((int)($this->session['lifetime'] ?? 120))
            ->getTimestamp();

        return new Cookie(
            Tessera::cookie(),
            $this->createToken($userId, $csrfToken, $expiration),
            $expiration,
            (string)($this->session['path'] ?? '/'),
            $this->session['domain'] ?? null,
            $this->session['secure'] ?? null,
            true,
            false,
            $this->session['same_site'] ?? Cookie::SAMESITE_LAX,
            (bool)($this->session['partitioned'] ?? false),
        );
    }

    /**
     * Create a JWT for the given user ID and CSRF token.
     *
     * @param string|int $userId User identifier
     * @param string $csrfToken CSRF token
     * @param int $expiration Unix expiry timestamp
     * @return string
     */
    protected function createToken(string|int $userId, string $csrfToken, int $expiration): string
    {
        return JWT::encode([
            'sub' => $userId,
            'csrf' => $csrfToken,
            'exp' => $expiration,
        ], Tessera::tokenEncryptionKey($this->encryptionKey), 'HS256');
    }
}
