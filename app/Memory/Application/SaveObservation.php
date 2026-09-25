<?php

namespace App\Memory\Application;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\Observation;

final class SaveObservation
{
    public function __construct(private MemoryRepository $memories) {}

    public function __invoke(Observation $observation): Observation
    {
        if ($observation->topicKey !== null) {
            $existing = $this->memories->findByTopicKey(
                $observation->userId,
                $observation->project,
                $observation->scope,
                $observation->topicKey,
            );

            if ($existing !== null) {
                return $this->memories->save($observation->withId($existing->id));
            }
        }

        return $this->memories->save($observation);
    }
}
