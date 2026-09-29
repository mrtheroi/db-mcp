<?php

namespace App\Memory\Application;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\PromptRepository;

final class MergeProjects
{
    public function __construct(
        private MemoryRepository $memories,
        private PromptRepository $prompts,
    ) {}

    /**
     * Move the rows as they are: topic keys that collide in the target
     * project are only counted, so the user can resolve them.
     *
     * @return array{observations: int, prompts: int, collisions: int}
     */
    public function __invoke(string $from, string $to, ?int $userId = null): array
    {
        $collisions = $this->memories->countTopicKeyCollisions($from, $to, $userId);

        return [
            'collisions' => $collisions,
            'observations' => $this->memories->moveToProject($from, $to, $userId),
            'prompts' => $this->prompts->moveToProject($from, $to, $userId),
        ];
    }
}
