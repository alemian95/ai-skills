<?php

declare(strict_types=1);

namespace App\Http\Handler;

use App\Greeting\Greeter;
use App\Greeting\PersonName;
use App\Greeting\Tone;
use App\Http\JsonResponder;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler sottile: traduce HTTP → tipi di dominio, delega, traduce il risultato → HTTP.
 * La validazione dell'input esterno avviene qui, al confine.
 */
final readonly class HelloHandler implements RequestHandlerInterface
{
    public function __construct(
        private Greeter $greeter,
        private JsonResponder $json,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $rawName = $request->getAttribute('name');
        if (!is_string($rawName)) {
            throw new LogicException('Attributo di rotta "name" mancante: controlla config/routes.php');
        }

        $rawTone = $request->getQueryParams()['tone'] ?? Tone::Casual->value;
        $tone = is_string($rawTone) ? Tone::tryFrom($rawTone) : null;
        if ($tone === null) {
            return $this->json->respond(['error' => 'Parametro "tone" non valido'], 422);
        }

        // Cattura solo l'eccezione che sai gestire; tutto il resto sale fino a ErrorHandlerMiddleware.
        try {
            $name = new PersonName($rawName);
        } catch (InvalidArgumentException $e) {
            return $this->json->respond(['error' => $e->getMessage()], 422);
        }

        return $this->json->respond(['message' => $this->greeter->greet($name, $tone)]);
    }
}
