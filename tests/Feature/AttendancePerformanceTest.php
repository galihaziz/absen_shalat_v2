<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendancePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_table_has_an_index_for_date_range_queries(): void
    {
        $this->assertTrue(Schema::hasIndex('absensi', 'absensi_tanggal_index'));

        $queryPlan = DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM absensi WHERE tanggal BETWEEN ? AND ?',
            ['2026-10-01', '2026-10-31']
        );
        $details = implode(' ', array_map(fn ($step): string => $step->detail, $queryPlan));

        $this->assertStringContainsString('absensi_tanggal_index', $details);
    }
}
