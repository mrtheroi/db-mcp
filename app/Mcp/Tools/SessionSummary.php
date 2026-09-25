<?php

namespace App\Mcp\Tools;

use App\Memory\Application\SaveObservation;
use App\Memory\Domain\Observation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Save a summary of the current session before ending it, so the next session can resume where this one left off.')]
class SessionSummary extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, SaveObservation $saveObservation): Response
    {
        $request->validate([
            'session_id' => ['required'],
            'project' => ['required'],
            'content' => ['required'],
        ]);

        $project = $request->get('project');

        $saveObservation(new Observation(
            userId: $request->user()->id,
            sessionId: $request->get('session_id'),
            type: 'session_summary',
            title: "Session summary: {$project}",
            content: $request->get('content'),
            project: $project,
            scope: 'project',
            topicKey: null,
        ));

        return Response::text('Session summary saved.');
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => $schema->string()->description('Current session identifier.')->required(),
            'project' => $schema->string()->description('Project the session worked on.')->required(),
            'content' => $schema->string()->description('Markdown with sections: Goal, Discoveries, Accomplished, Next Steps, Relevant Files.')->required(),
        ];
    }
}
