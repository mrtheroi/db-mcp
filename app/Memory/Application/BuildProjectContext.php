<?php

namespace App\Memory\Application;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\Observation;
use App\Memory\Domain\ProjectName;

final class BuildProjectContext
{
    public function __construct(private MemoryRepository $memories) {}

    public function __invoke(int $userId, ?string $project): string
    {
        $project = ProjectName::normalize($project);

        $latestSession = $this->memories->latestSessionSummary($userId, $project);
        $knowledge = $this->memories->withTopicKey($userId, $project, 20);
        $recent = $this->memories->recentWithoutTopicKey($userId, $project, 10);

        if ($latestSession === null && $knowledge === [] && $recent === []) {
            return "No context found for project {$project}.";
        }

        $sections = [];

        if ($latestSession !== null) {
            $sections[] = "## Latest session\n#{$latestSession->id} [{$latestSession->type}] {$latestSession->title}\n{$latestSession->content}";
        }

        if ($knowledge !== []) {
            $sections[] = "## Project knowledge\n".implode("\n", array_map(
                fn (Observation $observation) => "- #{$observation->id} [{$observation->type}] {$observation->title}: {$this->preview($observation->content)}",
                $knowledge,
            ));
        }

        if ($recent !== []) {
            $sections[] = "## Recent memories\n".implode("\n", array_map(
                fn (Observation $observation) => "- #{$observation->id} [{$observation->type}] {$observation->title}",
                $recent,
            ));
        }

        $sections[] = 'Use get-memory with an id to read a memory in full.';

        return implode("\n\n", $sections);
    }

    private function preview(string $content): string
    {
        $flat = preg_replace('/\s*\R\s*/u', ' ', trim($content));

        return mb_strlen($flat) > 300 ? mb_substr($flat, 0, 300).'…' : $flat;
    }
}
