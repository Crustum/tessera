<?php
declare(strict_types=1);

namespace Crustum\Tessera\Exception;

/**
 * Thrown when the request auth token does not match the session auth token.
 *
 * @phpstan-consistent-constructor
 */
class InvalidAuthTokenException extends AuthorizationException
{
    /**
     * Create an exception for mismatched auth tokens.
     *
     * @return static
     */
    public static function different(): static
    {
        return new static(
            'The provided auth token for the request is different from the session auth token.',
        );
    }
}
