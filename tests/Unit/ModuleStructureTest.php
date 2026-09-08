<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FATimesheets;

use PHPUnit\Framework\TestCase;

class ModuleStructureTest extends TestCase
{
    private string $moduleDir;
    
    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
    }
    
    public function testIncludesDirectoryExists(): void
    {
        $this->assertDirectoryExists($this->moduleDir . '/includes');
    }
    
    public function testTimesheetDbIncExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/timesheet_db.inc');
    }
    
    public function testTimesheetDbContainsFunctions(): void
    {
        $content = file_get_contents($this->moduleDir . '/includes/timesheet_db.inc');
        $this->assertNotEmpty($content);
    }
    
    public function testProjectDcsExists(): void
    {
        $this->assertDirectoryExists($this->moduleDir . '/ProjectDcs');
    }
}
