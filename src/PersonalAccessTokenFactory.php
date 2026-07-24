<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\Core\Configure;
use Laminas\Diactoros\Response;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issues personal access tokens via the personal_access grant.
 */
class PersonalAccessTokenFactory
{
    /**
     * Bind the League authorization server used for the personal_access grant.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     */
    public function __construct(
        protected AuthorizationServer $server,
    ) {
    }

    /**
     * Create a personal access token for the given user.
     *
     * @param string|int $userId User identifier
     * @param string $name Token name
     * @param list<string> $scopes Scopes
     * @param string $provider User provider name
     * @return \Crustum\Tessera\PersonalAccessTokenResult
     */
    public function make(string|int $userId, string $name, array $scopes, string $provider): PersonalAccessTokenResult
    {
        return new PersonalAccessTokenResult(
            $this->dispatchRequestToAuthorizationServer(
                $this->createRequest($userId, $name, $scopes, $provider),
            ),
        );
    }

    /**
     * Build the personal_access grant request.
     *
     * @param string|int $userId User identifier
     * @param string $name Token name
     * @param list<string> $scopes Scopes
     * @param string $provider User provider name
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createRequest(string|int $userId, string $name, array $scopes, string $provider): ServerRequestInterface
    {
        $url = (string)(Configure::read('App.fullBaseUrl') ?? 'http://localhost');

        return (new PsrHttpFactory())->createRequest(Request::create($url, 'POST', [
            'grant_type' => 'personal_access',
            'provider' => $provider,
            'user_id' => $userId,
            'scope' => implode(' ', $scopes),
            'name' => $name,
        ]));
    }

    /**
     * Dispatch the request to the authorization server and decode the JSON body.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return array<string, mixed>
     */
    protected function dispatchRequestToAuthorizationServer(ServerRequestInterface $request): array
    {
        $decoded = json_decode(
            $this->server->respondToAccessTokenRequest($request, new Response())->getBody()->__toString(),
            true,
        );

        return is_array($decoded) ? $decoded : [];
    }
}
