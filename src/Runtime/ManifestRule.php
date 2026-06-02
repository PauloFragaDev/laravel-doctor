<?php

declare(strict_types=1);

namespace LaravelDoctor\Runtime;

use LaravelDoctor\Diagnostics\Severity;

interface ManifestRule
{
    public function id(): string;

    public function title(): string;

    /** Una de las constantes de LaravelDoctor\Diagnostics\Categories. */
    public function category(): string;

    public function severity(): Severity;

    public function recommendation(): string;

    public function check(RuntimeManifest $manifest, ManifestRuleContext $context): void;
}
