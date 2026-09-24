<?php

declare(strict_types=1);

namespace App\Greeting;

/**
 * Servizio di dominio: nessuna dipendenza da HTTP, container o ambiente. Si testa con new Greeter().
 */
final readonly class Greeter
{
    public function greet(PersonName $name, Tone $tone = Tone::Casual): string
    {
        return "{$tone->salutation()}, {$name->value}!";
    }
}
