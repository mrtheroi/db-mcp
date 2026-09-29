<?php

namespace App\Memory\Domain;

interface PromptRepository
{
    public function save(UserPrompt $prompt): void;

    /**
     * Move prompts to another project, returning how many were moved.
     * A null user moves the rows of every user.
     */
    public function moveToProject(string $from, string $to, ?int $userId): int;
}
