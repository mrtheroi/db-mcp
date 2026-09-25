<?php

namespace App\Memory\Infrastructure\Persistence;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\Observation;

final class EloquentMemoryRepository implements MemoryRepository
{
    public function save(Observation $observation): Observation
    {
        $record = ObservationRecord::updateOrCreate(['id' => $observation->id], [
            'user_id' => $observation->userId,
            'session_id' => $observation->sessionId,
            'type' => $observation->type,
            'title' => $observation->title,
            'content' => $observation->content,
            'project' => $observation->project,
            'scope' => $observation->scope,
            'topic_key' => $observation->topicKey,
        ]);

        return $this->toDomain($record);
    }

    public function find(int $id): ?Observation
    {
        $record = ObservationRecord::find($id);

        return $record ? $this->toDomain($record) : null;
    }

    public function findByTopicKey(int $userId, ?string $project, string $scope, string $topicKey): ?Observation
    {
        $record = ObservationRecord::where('user_id', $userId)
            ->where('project', $project)
            ->where('scope', $scope)
            ->where('topic_key', $topicKey)
            ->first();

        return $record ? $this->toDomain($record) : null;
    }

    public function search(int $userId, string $query, int $limit, ?string $project = null): array
    {
        return ObservationRecord::where('user_id', $userId)
            ->when($project, fn ($builder) => $builder->where('project', $project))
            ->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$query])
            ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('english', ?)) DESC", [$query])
            ->limit($limit)
            ->get()
            ->map(fn (ObservationRecord $record) => $this->toDomain($record))
            ->all();
    }

    public function recent(int $userId, string $project, int $limit): array
    {
        return ObservationRecord::where('user_id', $userId)
            ->where('project', $project)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ObservationRecord $record) => $this->toDomain($record))
            ->all();
    }

    private function toDomain(ObservationRecord $record): Observation
    {
        return new Observation(
            userId: $record->user_id,
            sessionId: $record->session_id,
            type: $record->type,
            title: $record->title,
            content: $record->content,
            project: $record->project,
            scope: $record->scope,
            topicKey: $record->topic_key,
            id: $record->id,
        );
    }
}
