<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_audit_logs_are_pruned(): void
    {
        AuditLog::create([
            'action' => 'old.event',
            'created_at' => now()->subDays(400),
        ]);
        AuditLog::create([
            'action' => 'recent.event',
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('audit:prune', ['--days' => 365])->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.event']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recent.event']);
    }
}
