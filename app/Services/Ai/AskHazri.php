<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AttendanceStatus;
use App\Models\AiQuery;
use App\Support\Brand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * "Ask Haziri" — natural language → SQL over the curated views (spec §12.1).
 * Model writes SQL in a JSON envelope → SqlGuard → read-only execution (5 s) → table. One repair attempt on failure.
 */
final class AskHazri
{
    private const SCHEMA = <<<'TXT'
        v_employees(employee_id, code, name, branch, department, designation, employment_type, status, gender, joined_on, left_on, work_mode)
        v_attendance_days(employee_id, code, name, branch, department, date, status, late_min, early_out_min, worked_min, overtime_min, is_late 0/1, missing_out 0/1)
        v_leave(employee_id, code, name, leave_code, leave_type, is_paid, from_date, to_date, day_part, days, status)
        v_punches_summary(employee_id, local_date, punch_count, first_punch_utc, last_punch_utc, sources)
        TXT;

    public function __construct(private readonly AiGateway $ai) {}

    /** @return array{ok: bool, sql?: string, columns?: list<string>, rows?: list<array<string, mixed>>, error?: string, query_id?: int} */
    public function ask(string $question, int $userId): array
    {
        // Spec §12.1c: generated SQL only ever runs as the read-only user (SELECT on the four views, nothing else).
        if (! config('hazri.ai.readonly_db_username')) {
            return ['ok' => false, 'error' => __(':assistant needs the read-only database user (AI_DB_READONLY_USERNAME). Please contact support.', ['assistant' => Brand::assistantName()])];
        }
        $statuses = implode(', ', array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::cases()));
        $system = "You translate HR questions about attendance into one MariaDB SELECT statement.\n"
            . "Use ONLY these read-only views:\n" . self::SCHEMA . "\n"
            . "attendance status values: {$statuses}. employee status: active, on_leave, left. leave status: pending, approved, rejected, cancelled.\n"
            . 'Dates are Y-m-d. Today is ' . now(tenant('timezone'))->format('Y-m-d') . ".\n"
            . 'Rules: a single SELECT (CTEs allowed), no comments, no semicolons, no other tables, human-readable column aliases, '
            . 'LIMIT at most ' . SqlGuard::MAX_ROWS . '. If the question cannot be answered from these views, set sql to an empty string and explain in note.';
        $schema = [
            'type' => 'object',
            'properties' => ['sql' => ['type' => 'string'], 'note' => ['type' => 'string']],
            'required' => ['sql', 'note'],
            'additionalProperties' => false,
        ];

        $prompt = $question;
        $error = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $content = [['type' => 'text', 'text' => $attempt === 0 ? $question
                : "Question: {$question}\nYour previous SQL was rejected: {$error}\nReturn a corrected query."]];
            $result = $this->ai->ask('ask', $prompt, $system, $content, $schema, 'medium', $userId, 2000);
            $queryId = AiQuery::query()->latest('id')->value('id');
            if (! $result->ok) {
                return ['ok' => false, 'error' => $result->error, 'query_id' => $queryId];
            }
            $sql = trim((string) ($result->data['sql'] ?? ''));
            if ($sql === '') {
                return ['ok' => false, 'error' => (string) ($result->data['note'] ?? 'That question cannot be answered from attendance data.'), 'query_id' => $queryId];
            }
            $guard = SqlGuard::check($sql);
            if (! $guard['ok']) {
                $error = $guard['error'];
                AiQuery::whereKey($queryId)->update(['generated_sql' => $sql, 'status' => 'rejected', 'error' => $error]);

                continue;
            }
            try {
                $start = microtime(true);
                $rows = $this->run($guard['sql']);
                AiQuery::whereKey($queryId)->update([
                    'generated_sql' => $guard['sql'], 'row_count' => count($rows), 'status' => 'ok',
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                ]);

                return ['ok' => true, 'sql' => $guard['sql'], 'columns' => $rows ? array_keys($rows[0]) : [], 'rows' => $rows, 'query_id' => $queryId];
            } catch (\Throwable $e) {
                $error = 'database error: ' . mb_substr($e->getMessage(), 0, 300);
                AiQuery::whereKey($queryId)->update(['generated_sql' => $guard['sql'], 'status' => 'failed', 'error' => $error]);
            }
        }

        return ['ok' => false, 'error' => __('Sorry, I could not build a safe query for that question.')];
    }

    /** @return list<array<string, mixed>> */
    private function run(string $sql): array
    {
        $connection = $this->readonlyConnection();
        $db = DB::connection($connection);
        try {
            $db->statement('SET SESSION max_statement_time = 5'); // MariaDB (seconds)
        } catch (\Throwable) {
            $db->statement('SET SESSION max_execution_time = 5000'); // MySQL 8 (ms)
        }
        $db->statement('START TRANSACTION READ ONLY');
        try {
            return array_map(fn ($r) => (array) $r, $db->select($sql));
        } finally {
            $db->statement('ROLLBACK');
        }
    }

    /**
     * A separate connection to the tenant DB — never the app's own, so the READ ONLY transaction can't commit or
     * disturb anything in flight — using the read-only user when AI_DB_READONLY_* is configured (spec §14.4).
     */
    private function readonlyConnection(): string
    {
        Config::set('database.connections.ai_readonly', array_merge(Config::get('database.connections.tenant'), [
            'username' => config('hazri.ai.readonly_db_username'), 'password' => config('hazri.ai.readonly_db_password'),
        ]));
        DB::purge('ai_readonly');

        return 'ai_readonly';
    }
}
