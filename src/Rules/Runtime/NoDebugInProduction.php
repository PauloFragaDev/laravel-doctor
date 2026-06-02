<?php

declare(strict_types=1);

namespace LaravelDoctor\Rules\Runtime;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Runtime\ManifestRule;
use LaravelDoctor\Runtime\ManifestRuleContext;
use LaravelDoctor\Runtime\RuntimeManifest;

final class NoDebugInProduction implements ManifestRule
{
    public function id(): string { return 'no-debug-in-production'; }

    public function title(): string { return 'APP_DEBUG activado en producción'; }

    public function category(): string { return Categories::SECURITY; }

    public function severity(): Severity { return Severity::Error; }

    public function recommendation(): string
    {
        return 'Pon APP_DEBUG=false en producción: con debug activo se filtran stack traces, variables de entorno y datos sensibles.';
    }

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void
    {
        $env = $manifest->config['app.env'] ?? null;
        $debug = $manifest->config['app.debug'] ?? null;

        if ($env === 'production' && ($debug === true || $debug === 'true' || $debug === 1)) {
            $context->report('config/app.php', 0, 'APP_DEBUG está activo con APP_ENV=production: filtra información sensible.');
        }
    }
}
