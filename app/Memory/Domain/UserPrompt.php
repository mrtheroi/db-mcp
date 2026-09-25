<?php

namespace App\Memory\Domain;

final readonly class UserPrompt
{
    public function __construct(
        public int $userId,
        public string $sessionId,
        public ?string $project,
        public string $content,
    ) {}
}
