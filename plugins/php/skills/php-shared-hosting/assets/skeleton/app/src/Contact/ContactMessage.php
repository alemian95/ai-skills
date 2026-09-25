<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * Message submitted from the form, already validated: if the instance exists, the data is valid.
 */
final readonly class ContactMessage
{
    private function __construct(
        public string $name,
        public string $email,
        public string $message,
    ) {}

    /**
     * @throws ValidationFailed
     */
    public static function fromInput(mixed $input): self
    {
        $data = is_array($input) ? $input : [];
        $field = static fn(string $key): string => is_string($data[$key] ?? null) ? mb_trim($data[$key]) : '';
        $values = ['name' => $field('name'), 'email' => $field('email'), 'message' => $field('message')];

        $errors = [];
        if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
            $errors['name'] = 'The name must contain 1 to 100 characters';
        }
        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Invalid email address';
        }
        if ($values['message'] === '' || mb_strlen($values['message']) > 5000) {
            $errors['message'] = 'The message must contain 1 to 5000 characters';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors, $values);
        }

        return new self($values['name'], $values['email'], $values['message']);
    }
}
