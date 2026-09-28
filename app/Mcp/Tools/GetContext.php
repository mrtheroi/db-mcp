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

        $results = $memories->recent($request->user()->id, $project, 20);

        if ($results === []) {
            return Response::text("No context found for project {$project}.");
        }

        return Response::text(implode("\n\n", array_map(
            fn (Observation $observation) => "#{$observation->id} [{$observation->type}] {$observation->title}\n{$observation->content}",
            $results,
        )));
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
