<?php

use App\Models\LoginCode;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prunes a code that expired more than a day ago', function () {
    LoginCode::issue('ada@example.com');

    $this->travel(1)->days();
    $this->travel(11)->minutes();

    $this->artisan('model:prune', ['--model' => [LoginCode::class]])->assertSuccessful();

    expect(LoginCode::count())->toBe(0);
});

it('keeps a code that expired less than a day ago', function () {
    LoginCode::issue('ada@example.com');

    $this->travel(11)->minutes();

    $this->artisan('model:prune', ['--model' => [LoginCode::class]])->assertSuccessful();

    expect(LoginCode::count())->toBe(1);
});

it('schedules pruning of login codes daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains($event->command, 'model:prune')
            && str_contains($event->command, 'LoginCode'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
