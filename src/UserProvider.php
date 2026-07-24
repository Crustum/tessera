<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Authentication\Identifier\IdentifierInterface;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\Tessera\Contracts\OAuthenticatable;

/**
 * Resolves host users for OAuth token authentication.
 */
class UserProvider
{
    use LocatorAwareTrait;

    /**
     * @param \Authentication\Identifier\IdentifierInterface|null $identifier Optional identifier for credential flows
     * @param string $providerName Provider / guard name for client matching
     */
    public function __construct(
        protected ?IdentifierInterface $identifier = null,
        protected string $providerName = 'users',
    ) {
    }

    /**
     * Retrieve a user by primary identifier.
     *
     * @param string|int $identifier User id
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    public function retrieveById(string|int $identifier): ?OAuthenticatable
    {
        if ($this->identifier instanceof IdentifierInterface) {
            $user = $this->identifier->identify(['id' => $identifier]);
            if ($user instanceof OAuthenticatable) {
                return $user;
            }
        }

        $tableAlias = (string)Configure::read('Tessera.usersTable', 'Users');
        $table = $this->fetchTable($tableAlias);
        $primaryKey = $table->getPrimaryKey();
        $key = is_array($primaryKey) ? $primaryKey[0] : $primaryKey;

        $user = $table->find()
            ->where([$key => $identifier])
            ->first();

        return $user instanceof OAuthenticatable ? $user : null;
    }

    /**
     * Provider name used when matching OAuth clients.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }
}
