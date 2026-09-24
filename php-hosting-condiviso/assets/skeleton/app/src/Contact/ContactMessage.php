<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * Messaggio inviato dal form, già validato: se l'istanza esiste, i dati sono corretti.
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
            $errors['name'] = 'Il nome deve contenere da 1 a 100 caratteri';
        }
        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Indirizzo email non valido';
        }
        if ($values['message'] === '' || mb_strlen($values['message']) > 5000) {
            $errors['message'] = 'Il messaggio deve contenere da 1 a 5000 caratteri';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors, $values);
        }

        return new self($values['name'], $values['email'], $values['message']);
    }
}
