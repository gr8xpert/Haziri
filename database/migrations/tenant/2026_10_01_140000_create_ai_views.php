<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Curated read-only schema for "Ask Hazri" (spec §12.1). The AI only ever sees these four views. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_employees AS
            SELECT e.id AS employee_id, e.code, TRIM(CONCAT(e.first_name, ' ', COALESCE(e.last_name, ''))) AS name,
                   b.name AS branch, d.name AS department, g.name AS designation,
                   e.employment_type, e.status, e.gender, e.joined_on, e.left_on, e.work_mode
            FROM employees e
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations g ON g.id = e.designation_id
            WHERE e.deleted_at IS NULL
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_attendance_days AS
            SELECT a.employee_id, e.code, TRIM(CONCAT(e.first_name, ' ', COALESCE(e.last_name, ''))) AS name,
                   b.name AS branch, d.name AS department, a.date, a.status,
                   a.late_min, a.early_out_min, a.worked_min, a.overtime_min,
                   JSON_CONTAINS(COALESCE(a.flags, '[]'), '"late"') AS is_late,
                   JSON_CONTAINS(COALESCE(a.flags, '[]'), '"missing_out"') AS missing_out
            FROM attendance_days a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN departments d ON d.id = e.department_id
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_leave AS
            SELECT l.employee_id, e.code, TRIM(CONCAT(e.first_name, ' ', COALESCE(e.last_name, ''))) AS name,
                   t.code AS leave_code, t.name AS leave_type, t.is_paid, l.from_date, l.to_date, l.day_part, l.days, l.status
            FROM leave_requests l
            JOIN employees e ON e.id = l.employee_id
            JOIN leave_types t ON t.id = l.leave_type_id
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_punches_summary AS
            SELECT p.employee_id, p.local_date, COUNT(*) AS punch_count,
                   MIN(p.punched_at) AS first_punch_utc, MAX(p.punched_at) AS last_punch_utc,
                   GROUP_CONCAT(DISTINCT p.source) AS sources
            FROM punches p
            WHERE p.status = 'valid' AND p.deleted_at IS NULL AND p.employee_id IS NOT NULL
            GROUP BY p.employee_id, p.local_date
            SQL);
    }

    public function down(): void
    {
        foreach (['v_punches_summary', 'v_leave', 'v_attendance_days', 'v_employees'] as $v) {
            DB::statement("DROP VIEW IF EXISTS {$v}");
        }
    }
};
