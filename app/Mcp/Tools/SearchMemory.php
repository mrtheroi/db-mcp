<?php

namespace App\Mcp\Tools;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\Observation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search saved memories by keywords. Use it before starting a task that may have been done before, or when the user refers to past work.')]
class SearchMemory extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, MemoryRepository $memories): Response
    {
        $request->validate([
            'limit' => ['integer', 'min:1', 'max:20'],
        ]);

        $results = $memories->search($request->user()->id, $request->get('query'), (int) $request->get('limit', 10));

        if ($results === []) {
            return Response::text('No memories found.');
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
            'query' => $schema->string()->description('Keywords to search for, e.g. "auth tokens".')->required(),
            'limit' => $schema->integer()->description('Maximum number of results, from 1 to 20 (default 10).'),
        ];
    }
}
