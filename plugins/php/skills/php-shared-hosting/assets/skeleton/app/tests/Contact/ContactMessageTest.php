<?php

declare(strict_types=1);

namespace App\Tests\Contact;

use App\Contact\ContactMessage;
use App\Contact\ValidationFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContactMessage::class)]
#[CoversClass(ValidationFailed::class)]
final class ContactMessageTest extends TestCase
{
    #[Test]
    public function it_trims_valid_input(): void
    {
        $message = ContactMessage::fromInput(['name' => ' Élodie ', 'email' => 'e@example.com ', 'message' => " Hello\n"]);

        self::assertSame(['Élodie', 'e@example.com', 'Hello'], [$message->name, $message->email, $message->message]);
    }

    /**
     * @return iterable<string, array{mixed, list<string>}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'body not an array' => ['text', ['name', 'email', 'message']];
        yield 'invalid email' => [['name' => 'Anna', 'email' => 'anna@', 'message' => 'ok'], ['email']];
        yield 'name too long' => [['name' => str_repeat('é', 101), 'email' => 'a@b.it', 'message' => 'ok'], ['name']];
        yield 'wrong type' => [['name' => ['x'], 'email' => 'a@b.it', 'message' => 'ok'], ['name']];
    }

    /**
     * @param list<string> $fields
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_reports_every_invalid_field(mixed $input, array $fields): void
    {
        try {
            ContactMessage::fromInput($input);
            self::fail('ValidationFailed expected');
        } catch (ValidationFailed $e) {
            self::assertSame($fields, array_keys($e->errors));
        }
    }
}
