<?php

namespace App\Mcp\Tools;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\Observation;
use App\Memory\Domain\ProjectName;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Load recent memories and session summaries of a project. Call it at the start of a session to resume previous work.')]
class GetContext extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, MemoryRepository $memories): Response
    {
        $request->validate([
            'project' => ['required', 'max:255'],
        ]);

        $project = ProjectName::normalize($request->get('project'));

        $userId = $request->user()->id;
        $latestSession = $memories->latestSessionSummary($userId, $project);
        $knowledge = $memories->withTopicKey($userId, $project, 20);
        $recent = $memories->recentWithoutTopicKey($userId, $project, 10);

        if ($latestSession === null && $knowledge === [] && $recent === []) {
            return Response::text("No context found for project {$project}.");
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

        return Response::text(implode("\n\n", $sections));
    }

    private function preview(string $content): string
    {
        $flat = preg_replace('/\s*\R\s*/u', ' ', trim($content));

        return mb_strlen($flat) > 300 ? mb_substr($flat, 0, 300).'…' : $flat;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project to load context for.')->required(),
        ];
    }
}
