<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Controller\TransientTokenController;
use Crustum\Tessera\Exception\AuthenticationException;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * TransientTokenController unit tests.
 */
class TransientTokenControllerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @return void
     */
    public function testTokenCanBeRefreshed(): void
    {
        $cookieFactory = Double::for(ApiTokenCookieFactory::class);
        $cookieFactory->expects('make')->with(1, 'token')->returns(new Cookie('cookie', 'value'));

        $user = Double::for(OAuthenticatable::class);
        $user->allows('getIdentifier')->returns(1);

        $request = (new ServerRequest())
            ->withAttribute('identity', $user)
            ->withAttribute('csrfToken', 'token');

        $controller = new TransientTokenController($request);
        $controller->setRequest($request);

        $response = $controller->refresh($cookieFactory);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Refreshed.', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testRefreshFailsWhenCsrfAttributeMissing(): void
    {
        $cookieFactory = Double::for(ApiTokenCookieFactory::class);
        $cookieFactory->expects('make')->never();

        $user = Double::for(OAuthenticatable::class);
        $user->allows('getIdentifier')->returns(1);

        $request = (new ServerRequest())->withAttribute('identity', $user);

        $controller = new TransientTokenController($request);
        $controller->setRequest($request);

        $this->expectException(AuthenticationException::class);
        $controller->refresh($cookieFactory);
    }
}
