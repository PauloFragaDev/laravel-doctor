<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Reporting;

use LaravelDoctor\Diagnostics\Categories;
use LaravelDoctor\Diagnostics\Diagnostic;
use LaravelDoctor\Diagnostics\Severity;
use LaravelDoctor\Reporting\AgentReporter;
use LaravelDoctor\Score\ScoreResult;
use PHPUnit\Framework\TestCase;

final class AgentReporterTest extends TestCase
{
    public function test_emits_stable_json_contract(): void
    {
        $json = (new AgentReporter())->report(
            new ScoreResult(88, 'Needs work'),
            [new Diagnostic('no-env-outside-config', Categories::SECURITY, Severity::Error, 'app/X.php', 12, 'msg', 'rec')],
        );

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(88, $data['score']);
        $this->assertSame('Needs work', $data['label']);
        $this->assertCount(1, $data['diagnostics']);
        $this->assertSame([
            'id' => 'no-env-outside-config',
            'category' => 'security',
            'severity' => 'error',
            'file' => 'app/X.php',
            'line' => 12,
            'message' => 'msg',
            'recommendation' => 'rec',
        ], $data['diagnostics'][0]);
    }
}
