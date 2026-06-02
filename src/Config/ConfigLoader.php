<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use LaravelDoctor\Diagnostics\Severity;

final class ConfigLoader
{
    private const SEVERITIES = [
        'info' => Severity::Info,
        'warning' => Severity::Warning,
        'error' => Severity::Error,
    ];

    public function load(string $projectDir): DoctorConfig
    {
        $raw = $this->readRaw(rtrim($projectDir, '/'));
        if ($raw === null) {
            return DoctorConfig::empty();
        }

        $disabled = [];
        $overrides = [];
        foreach ((array) ($raw['rules'] ?? []) as $ruleId => $value) {
            $ruleId = (string) $ruleId;
            if ($value === false || $value === 'off') {
                $disabled[] = $ruleId;
                continue;
            }
            if (is_string($value) && isset(self::SEVERITIES[$value])) {
                $overrides[$ruleId] = self::SEVERITIES[$value];
            }
        }

        $exclude = array_values(array_map('strval', (array) ($raw['exclude'] ?? [])));

        return new DoctorConfig($disabled, $overrides, $exclude);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function readRaw(string $dir): ?array
    {
        $php = $dir . '/doctor.config.php';
        if (is_file($php)) {
            $data = require $php;

            return is_array($data) ? $data : null;
        }

        $json = $dir . '/doctor.config.json';
        if (is_file($json)) {
            $contents = file_get_contents($json);
            $data = $contents === false ? null : json_decode($contents, true);

            return is_array($data) ? $data : null;
        }

        return null;
    }
}
