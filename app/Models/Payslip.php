<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'sent_at' => 'immutable_datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
