<?php

declare(strict_types=1);

namespace Flowy\Tests\CLI;

use PHPUnit\Framework\TestCase;

final class FlowyCliTest extends TestCase
{
    public function testFlowyCliListCommandOutputsSuccess(): void
    {
        $cli = __DIR__ . '/../../bin/flowy';
        $output = null;
        $exitCode = null;
        exec('php ' . escapeshellarg($cli), $output, $exitCode);
        $outputText = implode("\n", $output);
        $this->assertSame(0, $exitCode, 'CLI should exit with code 0');
        $this->assertStringContainsString('Flowy CLI is working', $outputText);
    }
}
