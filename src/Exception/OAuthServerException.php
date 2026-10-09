<?php
declare(strict_types=1);

namespace Crustum\Tessera\Exception;

use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Wraps a League OAuth2 server exception for HTTP handling.
 *
 * Response conversion to Cake/Symfony responses is applied by controllers
 * (ConvertsPsrResponses) when this exception is handled.
 *
 * @phpstan-consistent-constructor
 */
class OAuthServerException extends RuntimeException
{
    /**
     * Wrap a League OAuth2 server exception for application handling.
     *
     * @param \League\OAuth2\Server\Exception\OAuthServerException $leagueException League exception
     * @param bool $useFragment Whether to encode error in the redirect fragment
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        protected LeagueException $leagueException,
        protected bool $useFragment = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $leagueException->getMessage(),
            (int)$leagueException->getCode(),
            $previous ?? $leagueException,
        );
    }

    /**
     * Underlying League exception.
     *
     * @return \League\OAuth2\Server\Exception\OAuthServerException
     */
    public function getLeagueException(): LeagueException
    {
        return $this->leagueException;
    }

    /**
     * Whether the OAuth error should be returned in the URI fragment.
     *
     * @return bool
     */
    public function useFragment(): bool
    {
        return $this->useFragment;
    }

    /**
     * Generate a PSR-7 HTTP response for this OAuth error.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Empty PSR-7 response prototype
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function generateHttpResponse(ResponseInterface $response): ResponseInterface
    {
        return $this->leagueException->generateHttpResponse($response, $this->useFragment);
    }

    /**
     * Create an exception for when login is required.
     *
     * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface $authRequest Auth request
     * @return static
     */
    public static function loginRequired(AuthorizationRequestInterface $authRequest): static
    {
        $redirectUri = $authRequest->getRedirectUri()
            ?? self::wrap($authRequest->getClient()->getRedirectUri())[0];

        $exception = new LeagueException(
            'The authorization server requires end-user authentication.',
            9,
            'login_required',
            401,
            'The user is not authenticated',
            $redirectUri,
        );

        $exception->setPayload([
            'state' => $authRequest->getState(),
            ...$exception->getPayload(),
        ]);

        return new static($exception, $authRequest->getGrantTypeId() === 'implicit');
    }

    /**
     * Create an exception for when consent is required.
     *
     * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface $authRequest Auth request
     * @return static
     */
    public static function consentRequired(AuthorizationRequestInterface $authRequest): static
    {
        $redirectUri = $authRequest->getRedirectUri()
            ?? self::wrap($authRequest->getClient()->getRedirectUri())[0];

        $exception = new LeagueException(
            'The authorization server requires end-user consent.',
            9,
            'consent_required',
            401,
            null,
            $redirectUri,
        );

        $exception->setPayload([
            'state' => $authRequest->getState(),
            ...$exception->getPayload(),
        ]);

        return new static($exception, $authRequest->getGrantTypeId() === 'implicit');
    }

    /**
     * Wrap a value as a list.
     *
     * @param mixed $value Value to wrap
     * @return list<mixed>
     */
    protected static function wrap(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return [$value];
    }
}
