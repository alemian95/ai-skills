<?php

declare(strict_types=1);

namespace App\Contact;

use InvalidArgumentException;

final class ValidationFailed extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors error message per field
     * @param array<string, string> $old submitted values, to repopulate the form
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $old,
    ) {
        parent::__construct('Invalid form data');
    }
}
