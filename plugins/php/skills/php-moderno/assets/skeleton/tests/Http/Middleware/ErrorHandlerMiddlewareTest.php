<?php

declare(strict_types=1);

namespace App\Tests\Http\Middleware;

use App\Config\Settings;
use App\Http\JsonResponder;
use App\Http\Middleware\ErrorHandlerMiddleware;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(ErrorHandlerMiddleware::class)]
#[CoversClass(JsonResponder::class)]
final class ErrorHandlerMiddlewareTest extends TestCase
{
    private TestHandler $log;

    protected function setUp(): void
    {
        $this->log = new TestHandler();
    }

    #[Test]
    public function it_hides_exception_details_outside_debug_mode_and_logs_them(): void
    {
        $response = $this->middleware(debug: false)->process(new ServerRequest(), $this->failingHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('segreto', (string) $response->getBody());
        self::assertTrue($this->log->hasErrorRecords());
    }

    #[Test]
    public function it_exposes_exception_details_in_debug_mode(): void
    {
        $response = $this->middleware(debug: true)->process(new ServerRequest(), $this->failingHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('RuntimeException', (string) $response->getBody());
    }

    private function middleware(bool $debug): ErrorHandlerMiddleware
    {
        return new ErrorHandlerMiddleware(
            new JsonResponder(new ResponseFactory(), new StreamFactory()),
            new Logger('test', [$this->log]),
            new Settings(debug: $debug, logStream: 'php://memory', logLevel: Level::Debug),
        );
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('dettaglio segreto');
            }
        };
    }
}
