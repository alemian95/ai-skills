<?php

declare(strict_types=1);

namespace App\Greeting;

use InvalidArgumentException;

/**
 * Value object: se esiste un'istanza, il valore è valido. La validazione avviene una volta sola, qui.
 */
final readonly class PersonName
{
    private const int MAX_LENGTH = 50;

    public string $value;

    public function __construct(string $value)
    {
        $value = mb_trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Il nome deve contenere da 1 a %d caratteri', self::MAX_LENGTH),
            );
        }

        if (preg_match('/^[\p{L}\p{M}\' -]+$/u', $value) !== 1) {
            throw new InvalidArgumentException('Il nome contiene caratteri non ammessi');
        }

        $this->value = mb_ucfirst($value);
    }
}
