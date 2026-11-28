<?php

namespace StellarWP\Slic\Test\Cli;

class CliVersionTest extends BaseTestCase
{
    public function test_help_output_starts_with_version(): void
    {
        $output = $this->slicExec('help');

        $this->assertStringContainsString('slic version 3.0.0', $output);
    }
}
