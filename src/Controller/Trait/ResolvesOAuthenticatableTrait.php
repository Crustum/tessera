<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Crustum\Tessera\Contracts\OAuthenticatable;

/**
 * Resolves an OAuthenticatable user from the request identity attribute.
 *
 * @phpstan-require-extends \Cake\Controller\Controller
 */
trait ResolvesOAuthenticatableTrait
{
    /**
     * Resolve the currently authenticated OAuth-capable identity.
     *
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected function currentIdentity(): ?OAuthenticatable
    {
        $identity = $this->getRequest()->getAttribute('identity');
        if ($identity instanceof OAuthenticatable) {
            return $identity;
        }

        if (is_object($identity) && method_exists($identity, 'getOriginalData')) {
            $data = $identity->getOriginalData();
            if ($data instanceof OAuthenticatable) {
                return $data;
            }
        }

        return null;
    }
}
