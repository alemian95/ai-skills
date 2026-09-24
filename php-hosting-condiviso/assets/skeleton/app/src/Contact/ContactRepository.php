<?php

declare(strict_types=1);

namespace App\Contact;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class ContactRepository
{
    public function __construct(private Connection $db) {}

    public function add(ContactMessage $message): void
    {
        $this->db->insert(
            'contact_messages',
            [
                'name' => $message->name,
                'email' => $message->email,
                'message' => $message->message,
                'created_at' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            ],
            ['created_at' => Types::DATETIME_IMMUTABLE],
        );
    }
}
