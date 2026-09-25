<?php

use App\Memory\Domain\Observation;
use App\Memory\Infrastructure\Persistence\EloquentMemoryRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it persists an observation and finds it by id', function () {
    $user = User::factory()->create();
    $repository = new EloquentMemoryRepository();

    $saved = $repository->save(new Observation(
        userId: $user->id,
        sessionId: 'session-1',
        type: 'decision',
        title: 'Use Postgres full-text search',
        content: 'tsvector + GIN index',
        project: 'dbmcp',
        scope: 'project',
        topicKey: null,
    ));

    $found = $repository->find($saved->id);

    expect($found->title)->toBe('Use Postgres full-text search')
        ->and($found->userId)->toBe($user->id);
});

test('it returns null when the observation does not exist', function () {
    $repository = new EloquentMemoryRepository();

    expect($repository->find(999))->toBeNull();
});

test('it ranks title matches above content matches', function () {
    $user = User::factory()->create();
    $repository = new EloquentMemoryRepository();
    $save = fn (string $title, string $content) => $repository->save(new Observation(
        userId: $user->id,
        sessionId: 'session-1',
        type: 'decision',
        title: $title,
        content: $content,
        project: 'dbmcp',
        scope: 'project',
        topicKey: null,
    ));

    $save('Deploy checklist', 'Rotate the Sanctum tokens after deploying');
    $save('Sanctum tokens are hashed', 'Stored with SHA-256');

    $titles = array_map(fn (Observation $o) => $o->title, $repository->search($user->id, 'sanctum tokens', limit: 10));

    expect($titles)->toBe(['Sanctum tokens are hashed', 'Deploy checklist']);
});

test('it returns at most the requested number of results', function () {
    $user = User::factory()->create();
    $repository = new EloquentMemoryRepository();

    foreach (['first', 'second', 'third'] as $position) {
        $repository->save(new Observation(
            userId: $user->id,
            sessionId: 'session-1',
            type: 'decision',
            title: "Sanctum tokens {$position}",
            content: 'Stored hashed',
            project: 'dbmcp',
            scope: 'project',
            topicKey: null,
        ));
    }

    expect($repository->search($user->id, 'sanctum', limit: 2))->toHaveCount(2);
});

test('it returns the most recent memories of a project first, up to the limit', function () {
    $user = User::factory()->create();
    $repository = new EloquentMemoryRepository();

    foreach (['oldest', 'middle', 'newest'] as $title) {
        remember($user, $title, 'Some content');
        $this->travel(1)->minutes();
    }

    $titles = array_map(fn (Observation $o) => $o->title, $repository->recent($user->id, 'dbmcp', limit: 2));

    expect($titles)->toBe(['newest', 'middle']);
});

test('it only returns matches of the given project', function () {
    $user = User::factory()->create();
    $repository = new EloquentMemoryRepository();

    remember($user, 'Sanctum tokens in dbmcp', 'Stored hashed', project: 'dbmcp');
    remember($user, 'Sanctum tokens elsewhere', 'Stored hashed', project: 'other');

    $titles = array_map(fn (Observation $o) => $o->title, $repository->search($user->id, 'sanctum', limit: 10, project: 'other'));

    expect($titles)->toBe(['Sanctum tokens elsewhere']);
});
