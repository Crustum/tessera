<?php
declare(strict_types=1);

namespace Crustum\Tessera\Exception;

use Authentication\Authenticator\UnauthenticatedException;

/**
 * Tessera authentication failure.
 */
class AuthenticationException extends UnauthenticatedException
{
}
