<?php

declare(strict_types=1);

namespace App\Contact;

use InvalidArgumentException;

final class ValidationFailed extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors messaggio d'errore per campo
     * @param array<string, string> $old valori inviati, per ripopolare il form
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $old,
    ) {
        parent::__construct('Dati del form non validi');
    }
}
