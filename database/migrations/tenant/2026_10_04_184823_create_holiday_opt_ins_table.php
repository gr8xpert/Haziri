<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional holidays apply only to the employees who take them (chosen by HR or by the employee in the app). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_opt_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['holiday_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_opt_ins');
    }
};
