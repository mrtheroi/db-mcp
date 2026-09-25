<?php

namespace App\Memory\Domain;

interface PromptRepository
{
    public function save(UserPrompt $prompt): void;
}
