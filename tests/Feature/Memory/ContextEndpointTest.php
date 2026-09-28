<?php

use App\Memory\Application\BuildProjectContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it rejects context requests without a token', function () {
    $this->getJson('/api/context?project=dbmcp')->assertUnauthorized();
});

test('it returns the get-context text of the project as plain text', function () {
    $user = User::factory()->create();
    $token = $user->createToken('claude-code')->plainTextToken;

    remember($user, 'Session one', 'Summary content', type: 'session_summary');
    remember($user, 'Auth model', 'Sanctum bearer tokens', topicKey: 'architecture/auth');
    remember($user, 'Fixed flaky token test', 'Root cause: clock drift');

    $response = $this->withToken($token)->get('/api/context?project=dbmcp');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee(['## Latest session', '## Project knowledge', '## Recent memories']);
    expect($response->getContent())->toBe(app(BuildProjectContext::class)($user->id, 'dbmcp'));
});

test('it never returns the memories of another user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $userToken = $user->createToken('claude-code')->plainTextToken;
    $strangerToken = $stranger->createToken('claude-code')->plainTextToken;

    remember($user, 'Own decision', 'Own content');
    remember($stranger, 'Stranger decision', 'Must never leak');

    $this->withToken($userToken)->get('/api/context?project=dbmcp')
        ->assertOk()
        ->assertSee('Own decision')
        ->assertDontSee('Stranger decision');

    $this->app['auth']->forgetGuards();

    $this->withToken($strangerToken)->get('/api/context?project=dbmcp')
        ->assertOk()
        ->assertSee('Stranger decision')
        ->assertDontSee('Own decision');
});

test('it requires a project', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $this->withToken($token)->getJson('/api/context')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project' => 'The project field is required.']);
});

test('it rejects a project name that is too long', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $this->withToken($token)->getJson('/api/context?project='.str_repeat('a', 256))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project' => 'The project field must not be greater than 255 characters.']);
});

test('it rejects a project that is not a string', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $this->withToken($token)->getJson('/api/context?project[]=dbmcp')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project' => 'The project field must be a string.']);
});

test('it returns the no-context message when the project has no context yet', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $response = $this->withToken($token)->get('/api/context?project=%20Brand-New%20');

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe('No context found for project brand-new.');
});

test('it limits each user to 60 context requests per minute', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    foreach (range(1, 60) as $request) {
        $this->withToken($token)->get('/api/context?project=dbmcp')->assertOk();
    }

    $this->withToken($token)->get('/api/context?project=dbmcp')->assertTooManyRequests();
});
