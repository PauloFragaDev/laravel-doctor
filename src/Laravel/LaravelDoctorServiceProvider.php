<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Se auto-descubre vía package discovery (composer.json → extra.laravel.providers) cuando
 * laravel-doctor está instalado como dependencia de la app. Solo registra el comando que
 * emite el manifiesto de runtime.
 */
final class LaravelDoctorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ManifestCommand::class]);
        }
    }
}
