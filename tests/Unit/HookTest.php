<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FA\Timesheets;

use PHPUnit\Framework\TestCase;

class HookTest extends TestCase
{
    public function testHooksFileExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../hooks.php'));
    }
}
