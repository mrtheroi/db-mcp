<?php

namespace App\Memory\Domain;

interface MemoryRepository
{
    public function save(Observation $observation): Observation;

    public function find(int $id): ?Observation;

    public function findByTopicKey(int $userId, ?string $project, string $scope, string $topicKey): ?Observation;

    /**
     * @return list<Observation>
     */
    public function search(int $userId, string $query, int $limit, ?string $project = null): array;

    public function latestSessionSummary(int $userId, string $project): ?Observation;

    /**
     * @return list<Observation>
     */
    public function withTopicKey(int $userId, string $project, int $limit): array;

    /**
     * @return list<Observation>
     */
    public function recentWithoutTopicKey(int $userId, string $project, int $limit): array;
}
