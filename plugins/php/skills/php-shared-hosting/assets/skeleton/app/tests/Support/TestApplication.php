<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Config\Settings;
use App\Database\Migrator;
use DI\ContainerBuilder;
use FilesystemIterator;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionPersistenceInterface;
use Monolog\Level;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Complete application with the production definitions, on a temporary data folder,
 * SQLite and in-memory sessions. Handles the session cookie across requests.
 */
final class TestApplication
{
    public readonly ContainerInterface $container;
    private readonly RequestHandlerInterface $app;
    private readonly string $varDir;
    private string $sessionId = '';

    public function __construct(private readonly string $scriptName = '/index.php')
    {
        $appDir = dirname(__DIR__, 2);
        $this->varDir = sys_get_temp_dir() . '/app-test-' . bin2hex(random_bytes(4));
        foreach (['', '/log', '/cache', '/sessions'] as $dir) {
            mkdir($this->varDir . $dir, 0o755, true);
        }

        $settings = new Settings(
            appDir: $appDir,
            publicDir: dirname($appDir),
            varDir: $this->varDir,
            debug: false,
            https: false,
            timezone: 'Europe/Rome',
            logLevel: Level::Debug,
            sessionName: 'test',
            database: ['driver' => 'pdo_sqlite', 'path' => $this->varDir . '/test.sqlite'],
        );

        $builder = new ContainerBuilder();
        $builder->addDefinitions($appDir . '/config/container.php', [
            Settings::class => $settings,
            SessionPersistenceInterface::class => new InMemorySessionPersistence(),
        ]);
        $this->container = $builder->build();

        $migrator = $this->container->get(Migrator::class);
        assert($migrator instanceof Migrator);
        $migrator->migrate();

        $app = $this->container->get(RequestHandlerInterface::class);
        assert($app instanceof RequestHandlerInterface);
        $this->app = $app;
    }

    /**
     * @param array<string, string> $body
     */
    public function request(string $method, string $path, array $body = []): ResponseInterface
    {
        $request = new ServerRequest(
            serverParams: ['SCRIPT_NAME' => $this->scriptName],
            uri: $path,
            method: $method,
            cookieParams: $this->sessionId === '' ? [] : [InMemorySessionPersistence::COOKIE => $this->sessionId],
            parsedBody: $body === [] ? null : $body,
        );
        $response = $this->app->handle($request);

        if (preg_match('/' . InMemorySessionPersistence::COOKIE . '=([0-9a-f]+)/', $response->getHeaderLine('Set-Cookie'), $m) === 1) {
            $this->sessionId = $m[1];
        }

        return $response;
    }

    public function removeFiles(): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->varDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            assert($item instanceof SplFileInfo);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->varDir);
    }
}
