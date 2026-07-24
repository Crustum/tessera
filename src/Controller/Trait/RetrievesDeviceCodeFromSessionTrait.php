<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Cake\Http\ServerRequest;
use Crustum\Tessera\Bridge\Client as BridgeClient;
use Crustum\Tessera\Bridge\DeviceCode as BridgeDeviceCode;
use Crustum\Tessera\Bridge\Scope as BridgeScope;
use Crustum\Tessera\Exception\InvalidAuthTokenException;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use RuntimeException;

/**
 * Restores the pending device code stashed in the session by the device consent screen.
 */
trait RetrievesDeviceCodeFromSessionTrait
{
    /**
     * Read and clear the device code previously stashed in the session.
     *
     * @param \Cake\Http\ServerRequest $request Request carrying the `auth_token` confirmation field
     * @return \League\OAuth2\Server\Entities\DeviceCodeEntityInterface
     * @throws \Crustum\Tessera\Exception\InvalidAuthTokenException
     * @throws \RuntimeException
     */
    protected function getDeviceCodeFromSession(ServerRequest $request): DeviceCodeEntityInterface
    {
        $session = $request->getSession();
        $submittedToken = $request->getData('auth_token');
        $sessionToken = $session->read('authToken');
        $session->delete('authToken');

        if (!is_string($submittedToken) || $submittedToken === '' || $sessionToken !== $submittedToken) {
            $session->delete('deviceCode');

            throw InvalidAuthTokenException::different();
        }

        $serialized = $session->read('deviceCode');
        $session->delete('deviceCode');

        if (!is_string($serialized) || $serialized === '') {
            throw new RuntimeException('Device code was not present in the session.');
        }

        $deviceCode = unserialize($serialized, ['allowed_classes' => [
            BridgeDeviceCode::class,
            BridgeClient::class,
            BridgeScope::class,
            DateTimeImmutable::class,
        ]]);

        if (!$deviceCode instanceof DeviceCodeEntityInterface) {
            throw new RuntimeException('Device code was not present in the session.');
        }

        return $deviceCode;
    }
}
