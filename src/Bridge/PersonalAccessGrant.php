<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Crustum\Tessera\Tessera;
use DateInterval;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Personal access token grant.
 */
class PersonalAccessGrant extends AbstractGrant
{
    /**
     * @inheritDoc
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        $userIdentifier = $this->getRequestParameter('user_id', $request);
        if (!$userIdentifier) {
            throw OAuthServerException::invalidRequest('user_id');
        }

        $provider = $this->getRequestParameter('provider', $request);
        if (!$provider) {
            throw OAuthServerException::invalidRequest('provider');
        }

        if (!$this->clientRepository instanceof ClientRepository) {
            throw OAuthServerException::invalidRequest('client');
        }

        $client = $this->clientRepository->getPersonalAccessClientEntity($provider);
        if (!$client instanceof ClientEntityInterface) {
            throw OAuthServerException::invalidClient($request);
        }

        $scopes = $this->validateScopes(
            $this->getRequestParameter('scope', $request, $this->defaultScope),
        );

        $scopes = $this->scopeRepository->finalizeScopes(
            $scopes,
            $this->getIdentifier(),
            $client,
            $userIdentifier,
        );

        $accessToken = $this->issueAccessToken(
            $accessTokenTTL,
            $client,
            $userIdentifier,
            $scopes,
        );

        $this->getEmitter()->emit(
            new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken),
        );

        Tessera::tokensTable()->updateAll(
            ['name' => $this->getRequestParameter('name', $request)],
            ['id' => $accessToken->getIdentifier()],
        );

        $responseType->setAccessToken($accessToken);

        return $responseType;
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return 'personal_access';
    }
}
