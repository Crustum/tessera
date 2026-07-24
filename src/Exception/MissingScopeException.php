<?php
declare(strict_types=1);

namespace Crustum\Tessera\Exception;

/**
 * Thrown when a token is missing one or more required scopes.
 */
class MissingScopeException extends AuthorizationException
{
    /**
     * Scopes that the user did not have.
     *
     * @var list<string>
     */
    protected array $scopes;

    /**
     * Record which required scopes were missing from the token.
     *
     * @param list<string>|string $scopes Missing scopes
     * @param string $message Exception message
     */
    public function __construct(array|string $scopes = [], string $message = 'Invalid scope(s) provided.')
    {
        parent::__construct($message);

        $this->scopes = is_array($scopes) ? $scopes : [$scopes];
    }

    /**
     * Get the scopes that the user did not have.
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
