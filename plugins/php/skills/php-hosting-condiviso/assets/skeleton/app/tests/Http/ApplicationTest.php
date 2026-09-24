<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\Support\TestApplication;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ApplicationTest extends TestCase
{
    private TestApplication $app;

    protected function setUp(): void
    {
        $this->app = new TestApplication();
    }

    protected function tearDown(): void
    {
        $this->app->removeFiles();
    }

    #[Test]
    public function home_is_rendered_with_security_headers_and_versioned_assets(): void
    {
        $response = $this->app->request('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString("frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertMatchesRegularExpression('#href="/assets/css/app\.css\?v=\d+"#', (string) $response->getBody());
    }

    #[Test]
    public function unknown_route_and_wrong_method_render_html_error_pages(): void
    {
        $notFound = $this->app->request('GET', '/missing');
        self::assertSame(404, $notFound->getStatusCode());
        self::assertStringContainsString('Pagina non trovata', (string) $notFound->getBody());

        $notAllowed = $this->app->request('DELETE', '/contatti');
        self::assertSame(405, $notAllowed->getStatusCode());
        self::assertSame('GET, POST', $notAllowed->getHeaderLine('Allow'));
    }

    #[Test]
    public function post_without_csrf_token_is_rejected(): void
    {
        $response = $this->app->request('POST', '/contatti', ['name' => 'Anna', 'email' => 'anna@example.com', 'message' => 'Ciao']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->storedMessages());
    }

    #[Test]
    public function valid_message_is_stored_and_confirmed_once_after_redirect(): void
    {
        $token = $this->csrfToken();

        $response = $this->app->request('POST', '/contatti', [
            '_csrf' => $token, 'name' => 'Anna', 'email' => 'anna@example.com', 'message' => 'Ciao',
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/contatti', $response->getHeaderLine('Location'));
        self::assertSame(1, $this->storedMessages());

        self::assertStringContainsString('Messaggio inviato', (string) $this->app->request('GET', '/contatti')->getBody());
        self::assertStringNotContainsString('Messaggio inviato', (string) $this->app->request('GET', '/contatti')->getBody());

        $second = $this->app->request('POST', '/contatti', [
            '_csrf' => $token, 'name' => 'Anna', 'email' => 'anna@example.com', 'message' => 'Di nuovo',
        ]);
        self::assertSame(303, $second->getStatusCode(), 'lo stesso token vale per tutta la sessione');
    }

    #[Test]
    public function invalid_input_is_redisplayed_escaped_with_errors(): void
    {
        $token = $this->csrfToken();

        $response = $this->app->request('POST', '/contatti', [
            '_csrf' => $token, 'name' => '"><script>alert(1)</script>', 'email' => 'non-valida', 'message' => 'Ciao',
        ]);
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Indirizzo email non valido', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertSame(0, $this->storedMessages());
    }

    #[Test]
    public function the_application_works_from_a_subdirectory(): void
    {
        $app = new TestApplication(scriptName: '/sito/index.php');

        try {
            $html = (string) $app->request('GET', '/sito/contatti')->getBody();

            self::assertStringContainsString('action="/sito/contatti"', $html);
            self::assertMatchesRegularExpression('#href="/sito/assets/css/app\.css\?v=\d+"#', $html);
            self::assertSame(200, $app->request('GET', '/sito/index.php/contatti')->getStatusCode(), 'fallback con PATH_INFO');
        } finally {
            $app->removeFiles();
        }
    }

    private function csrfToken(): string
    {
        $html = (string) $this->app->request('GET', '/contatti')->getBody();
        if (preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $html, $m) !== 1) {
            self::fail('Campo CSRF non trovato nel form');
        }

        return $m[1];
    }

    private function storedMessages(): int
    {
        $db = $this->app->container->get(Connection::class);
        assert($db instanceof Connection);

        $count = $db->fetchOne('SELECT COUNT(*) FROM contact_messages');

        return is_numeric($count) ? (int) $count : -1;
    }
}
