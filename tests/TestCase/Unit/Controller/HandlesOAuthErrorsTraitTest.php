<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Exception\OAuthServerException;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use RuntimeException;

/**
 * HandlesOAuthErrorsTrait unit tests.
 */
class HandlesOAuthErrorsTraitTest extends TestCase
{
    /**
     * @return void
     */
    public function testShouldReturnCallbackResultIfNoErrorIsThrown(): void
    {
        $controller = new HandlesOAuthErrorsTraitTestStub();
        $response = new Response();

        $result = $controller->test(static fn(): Response => $response);

        $this->assertSame($response, $result);
    }

    /**
     * @return void
     */
    public function testShouldHandleOAuthServerException(): void
    {
        $controller = new HandlesOAuthErrorsTraitTestStub();

        $caught = null;

        try {
            $controller->test(static function (): void {
                throw new LeagueException('Error', 1, 'fatal');
            });
        } catch (OAuthServerException $oAuthServerException) {
            $caught = $oAuthServerException;
        }

        $this->assertInstanceOf(OAuthServerException::class, $caught);
        $this->assertSame('Error', $caught->getMessage());
        $this->assertSame(1, $caught->getCode());
        $this->assertInstanceOf(LeagueException::class, $caught->getPrevious());

        $httpResponse = $caught->generateHttpResponse(new PsrResponse());

        $this->assertJsonStringEqualsJsonString(
            '{"error":"fatal","error_description":"Error"}',
            (string)$httpResponse->getBody(),
        );
    }

    /**
     * @return void
     */
    public function testShouldIgnoreOtherExceptions(): void
    {
        $controller = new HandlesOAuthErrorsTraitTestStub();
        $exception = new RuntimeException('Exception occurred', 1);

        $this->expectException(RuntimeException::class);

        $controller->test(static function () use ($exception): void {
            throw $exception;
        });
    }
}
