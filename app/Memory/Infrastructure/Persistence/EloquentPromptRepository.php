<?php

namespace App\Memory\Infrastructure\Persistence;

use App\Memory\Domain\PromptRepository;
use App\Memory\Domain\UserPrompt;

final class EloquentPromptRepository implements PromptRepository
{
    public function save(UserPrompt $prompt): void
    {
        UserPromptRecord::create([
            'user_id' => $prompt->userId,
            'session_id' => $prompt->sessionId,
            'project' => $prompt->project,
            'content' => $prompt->content,
        ]);
    }

    public function moveToProject(string $from, string $to, ?int $userId): int
    {
        return UserPromptRecord::where('project', $from)
            ->when($userId, fn ($builder) => $builder->where('user_id', $userId))
            ->toBase()
            ->update(['project' => $to]);
    }
}
