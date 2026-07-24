<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Http\Response;
use Cake\Utility\Security;
use Crustum\Tessera\Bridge\DeviceCodeRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Controller\Trait\RendersConfiguredViewsTrait;
use Crustum\Tessera\Controller\Trait\ResolvesOAuthenticatableTrait;
use Crustum\Tessera\Controller\Trait\ResolvesTesseraScopesTrait;
use Crustum\Tessera\Exception\AuthenticationException;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;

/**
 * Authorizes a device to access the current identity's account.
 */
class DeviceAuthorizationController extends AppController
{
    use RendersConfiguredViewsTrait;
    use ResolvesOAuthenticatableTrait;
    use ResolvesTesseraScopesTrait;

    /**
     * Authorize a device by its user-facing code, then render the device consent screen.
     *
     * @param \Crustum\Tessera\Bridge\DeviceCodeRepository $deviceCodes Device codes
     * @param \Crustum\Tessera\ClientRepository $clients Clients
     * @return \Cake\Http\Response
     * @throws \Crustum\Tessera\Exception\AuthenticationException
     */
    public function index(DeviceCodeRepository $deviceCodes, ClientRepository $clients): Response
    {
        $request = $this->getRequest();
        $userCode = $request->getQuery('user_code');

        if (!is_string($userCode) || $userCode === '') {
            return $this->redirect(['_name' => 'tessera:device']);
        }

        $deviceCode = $deviceCodes->getDeviceCodeEntityByUserCode(str_replace('-', '', $userCode));

        if (!$deviceCode instanceof DeviceCodeEntityInterface) {
            return $this->redirect([
                '_name' => 'tessera:device',
                '?' => ['user_code' => $userCode, 'error' => 'invalid_code'],
            ]);
        }

        $identity = $this->currentIdentity();
        if (!$identity instanceof OAuthenticatable) {
            throw new AuthenticationException();
        }

        $deviceCode->setUserIdentifier((string)$identity->getIdentifier());

        $scopes = $this->tesseraScopesFrom($deviceCode->getScopes());
        $client = $clients->find($deviceCode->getClient()->getIdentifier());

        $authToken = Security::randomString(40);
        $session = $request->getSession();
        $session->write('authToken', $authToken);
        $session->write('deviceCode', serialize($deviceCode));

        return $this->renderConfiguredView(Tessera::$deviceAuthorizationView, [
            'client' => $client,
            'user' => $identity,
            'scopes' => $scopes,
            'request' => $request,
            'authToken' => $authToken,
        ]);
    }
}
