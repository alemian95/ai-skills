<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Greeter;
use App\Greeting\PersonName;
use App\Greeting\Tone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Greeter::class)]
#[CoversClass(PersonName::class)]
#[CoversClass(Tone::class)]
final class GreeterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Tone, string}>
     */
    public static function greetings(): iterable
    {
        yield 'informale' => ['mario', Tone::Casual, 'Ciao, Mario!'];
        yield 'formale' => ['anna', Tone::Formal, 'Buongiorno, Anna!'];
        yield 'multibyte' => ['élodie', Tone::Casual, 'Ciao, Élodie!'];
        yield 'spazi rimossi' => ['  luca  ', Tone::Casual, 'Ciao, Luca!'];
    }

    #[Test]
    #[DataProvider('greetings')]
    public function it_greets_with_the_requested_tone(string $name, Tone $tone, string $expected): void
    {
        self::assertSame($expected, new Greeter()->greet(new PersonName($name), $tone));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'vuoto' => ['   '];
        yield 'troppo lungo' => [str_repeat('a', 51)];
        yield 'caratteri non ammessi' => ['<script>'];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_invalid_names(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PersonName($name);
    }
}
