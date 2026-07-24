<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use RuntimeException;
use Throwable;

/**
 * League OAuth2 user repository (password grant).
 */
class UserRepository implements UserRepositoryInterface
{
    use LocatorAwareTrait;

    /**
     * Create a password-grant user repository.
     *
     * @param \Authentication\PasswordHasher\PasswordHasherInterface $hasher Password hasher
     */
    public function __construct(
        protected PasswordHasherInterface $hasher = new DefaultPasswordHasher(),
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity,
    ): ?UserEntityInterface {
        $provider = $clientEntity instanceof Client && $clientEntity->provider
            ? $clientEntity->provider
            : (Configure::read('Tessera.provider') ?? Configure::read('Tessera.usersTable') ?? 'Users');

        $users = $this->usersTable($provider);

        if (method_exists($users, 'findAndValidateForTessera')) {
            $found = $users->findAndValidateForTessera($username, $password, $clientEntity);

            return $found instanceof EntityInterface
                ? new User((string)$found->get('id'))
                : null;
        }

        if (method_exists($users, 'findForTessera')) {
            $found = $users->findForTessera($username, $clientEntity);
            $user = $found instanceof EntityInterface ? $found : null;
        } else {
            $found = $users->find()
                ->where(['email' => $username])
                ->first();
            $user = $found instanceof EntityInterface ? $found : null;
        }

        if (!$user instanceof EntityInterface) {
            return null;
        }

        if (method_exists($user, 'validateForTesseraPasswordGrant')) {
            return $user->validateForTesseraPasswordGrant($password)
                ? new User((string)$user->get('id'))
                : null;
        }

        $hash = (string)$user->get('password');

        return $this->hasher->check($password, $hash)
            ? new User((string)$user->get('id'))
            : null;
    }

    /**
     * Load the users table for the given provider alias.
     *
     * @param string $provider Table alias / provider name
     * @return \Cake\ORM\Table
     * @throws \RuntimeException When the provider table cannot be resolved
     */
    protected function usersTable(string $provider): Table
    {
        try {
            return $this->fetchTable($provider);
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                'Unable to determine authentication model from configuration.',
                0,
                $throwable,
            );
        }
    }
}
