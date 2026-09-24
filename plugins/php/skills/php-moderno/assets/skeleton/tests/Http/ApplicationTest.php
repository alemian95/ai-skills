<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Config\Settings;
use DI\ContainerBuilder;
use Laminas\Diactoros\ServerRequest;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test dell'intera pipeline: stesse definizioni del container di produzione,
 * con la sola configurazione sovrascritta.
 */
#[CoversNothing]
final class ApplicationTest extends TestCase
{
    private RequestHandlerInterface $app;

    protected function setUp(): void
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(
            dirname(__DIR__, 2) . '/config/container.php',
            [Settings::class => new Settings(debug: false, logStream: 'php://memory', logLevel: Level::Debug)],
        );

        $app = $builder->build()->get(RequestHandlerInterface::class);
        self::assertInstanceOf(RequestHandlerInterface::class, $app);
        $this->app = $app;
    }

    #[Test]
    public function health_check_answers_ok(): void
    {
        $response = $this->request('GET', '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok'], $this->json($response));
    }

    #[Test]
    public function hello_uses_route_parameter_and_query_string(): void
    {
        $response = $this->request('GET', '/hello/anna', ['tone' => 'formal']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(['message' => 'Buongiorno, Anna!'], $this->json($response));
    }

    #[Test]
    public function invalid_input_is_rejected_with_422(): void
    {
        self::assertSame(422, $this->request('GET', '/hello/anna', ['tone' => 'rude'])->getStatusCode());
        self::assertSame(422, $this->request('GET', '/hello/' . str_repeat('a', 51))->getStatusCode());
    }

    #[Test]
    public function unknown_route_and_wrong_method_are_handled_by_the_router(): void
    {
        self::assertSame(404, $this->request('GET', '/missing')->getStatusCode());

        $response = $this->request('POST', '/health');
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));
    }

    /**
     * @param array<string, string> $query
     */
    private function request(string $method, string $path, array $query = []): ResponseInterface
    {
        return $this->app->handle(new ServerRequest(method: $method, uri: $path, queryParams: $query));
    }

    /**
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
