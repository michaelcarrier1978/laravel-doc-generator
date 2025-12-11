<?php

namespace LaravelDocs\Generator;

use Illuminate\Support\ServiceProvider;
use LaravelDocs\Generator\Commands\GenerateControllerDocs;
use LaravelDocs\Generator\Commands\PublishToConfluence;
use LaravelDocs\Generator\Commands\TestConfluence;

class LaravelDocGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-doc-generator.php',
            'laravel-doc-generator'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateControllerDocs::class,
                PublishToConfluence::class,
                TestConfluence::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/laravel-doc-generator.php' => config_path('laravel-doc-generator.php'),
            ], 'config');
        }
    }
}
