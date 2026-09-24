<?php

declare(strict_types=1);

namespace App\Greeting;

enum Tone: string
{
    case Casual = 'casual';
    case Formal = 'formal';

    public function salutation(): string
    {
        return match ($this) {
            self::Casual => 'Ciao',
            self::Formal => 'Buongiorno',
        };
    }
}
