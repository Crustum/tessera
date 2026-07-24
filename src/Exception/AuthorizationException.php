<?php
declare(strict_types=1);

namespace Crustum\Tessera\Exception;

use Cake\Http\Exception\ForbiddenException;
use Throwable;

/**
 * Base authorization failure for Tessera.
 */
class AuthorizationException extends ForbiddenException
{
    /**
     * Build an authorization failure with HTTP 403 as the default code.
     *
     * @param string $message Exception message
     * @param int|null $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = '', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : null, $code ?? 403, $previous);
    }
}
