<?php

declare(strict_types=1);

namespace Tests\Unit;

use Keel\Core\Request;
use Keel\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testDispatchCallsRegisteredHandlerForMatchingRoute(): void
    {
        $router = new Router();
        $router->get('/hello/{name}', function (Request $request, string $name): string {
            return 'hello:' . $name . ':' . $request->method;
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello/keel';

        $request = new Request();
        $result = $router->dispatch($request);

        self::assertSame('hello:keel:GET', $result);
    }

    public function testHeadIsAnsweredByTheGetRoute(): void
    {
        $router = new Router();
        $router->get('/up', fn (Request $request): string => 'up:' . $request->method);
        $router->post('/form', fn (): string => 'posted');

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/up';

        self::assertSame('up:HEAD', $router->dispatch(new Request()));
    }
}