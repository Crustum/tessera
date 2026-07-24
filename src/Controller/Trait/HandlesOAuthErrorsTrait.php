<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Closure;
use Crustum\Tessera\Exception\OAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;

/**
 * Wraps League OAuth2 server errors into the Tessera exception type.
 */
trait HandlesOAuthErrorsTrait
{
    /**
     * Run the given callback, converting League OAuth2 errors as they occur.
     *
     * @template TResult
     * @param \Closure(): TResult $callback Callback to execute
     * @param bool $useFragment Whether the error should be encoded in the redirect fragment
     * @return TResult
     * @throws \Crustum\Tessera\Exception\OAuthServerException
     */
    protected function withErrorHandling(Closure $callback, bool $useFragment = false): mixed
    {
        try {
            return $callback();
        } catch (LeagueOAuthServerException $leagueoAuthServerException) {
            throw new OAuthServerException($leagueoAuthServerException, $useFragment);
        }
    }
}
