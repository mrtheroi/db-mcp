<?php

namespace App\Providers;

use App\Memory\Domain\MemoryRepository;
use App\Memory\Domain\PromptRepository;
use App\Memory\Infrastructure\Persistence\EloquentMemoryRepository;
use App\Memory\Infrastructure\Persistence\EloquentPromptRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MemoryRepository::class, EloquentMemoryRepository::class);
        $this->app->bind(PromptRepository::class, EloquentPromptRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(60)->by($request->user()->id));
    }
}
