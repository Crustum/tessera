<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Authentication\IdentityInterface;
use Crustum\Tessera\Controller\AuthorizationController;
use Crustum\Tessera\Model\Entity\Client;

/**
 * AuthorizationController test double with controllable granted-scope behavior.
 */
class AuthorizationControllerTestStub extends AuthorizationController
{
    /**
     * @var bool
     */
    public bool $grantedScopes = false;

    /**
     * @param \Authentication\IdentityInterface $identity Consenting identity
     * @param \Crustum\Tessera\Model\Entity\Client $client Client requesting access
     * @param list<\Crustum\Tessera\Scope> $scopes Requested scopes
     * @return bool
     */
    protected function hasGrantedScopes(IdentityInterface $identity, Client $client, array $scopes): bool
    {
        return $this->grantedScopes;
    }
}
