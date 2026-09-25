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

    /**
     * @return list<Observation>
     */
    public function recent(int $userId, string $project, int $limit): array;
}
