<?php

declare(strict_types=1);

namespace App\Contact;

use App\Http\Flash;
use App\View\View;
use LogicException;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET mostra il form; POST lo valida e, se corretto, salva e reindirizza (Post/Redirect/Get):
 * ricaricare la pagina di conferma non ripete l'invio. Il token CSRF lo verifica CsrfMiddleware.
 */
final readonly class ContactHandler implements RequestHandlerInterface
{
    public function __construct(
        private ContactRepository $contacts,
        private View $view,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $this->form($request);
        }

        try {
            $message = ContactMessage::fromInput($request->getParsedBody());
        } catch (ValidationFailed $e) {
            return $this->form($request, $e->errors, $e->old, 422);
        }

        $this->contacts->add($message);

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (!$session instanceof SessionInterface) {
            throw new LogicException('SessionMiddleware mancante nella pipeline');
        }
        Flash::add($session, 'Messaggio inviato, grazie!');

        return $this->view->redirect($request, '/contatti');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function form(ServerRequestInterface $request, array $errors = [], array $old = [], int $status = 200): ResponseInterface
    {
        return $this->view->render($request, 'contact.html.twig', [
            'errors' => $errors,
            'old' => [...['name' => '', 'email' => '', 'message' => ''], ...$old],
        ], $status);
    }
}
