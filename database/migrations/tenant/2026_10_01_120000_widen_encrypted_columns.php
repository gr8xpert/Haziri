<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** salary_structures.bank_account is stored encrypted; ciphertext can exceed 255 chars. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_structures', fn (Blueprint $t) => $t->text('bank_account')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('salary_structures', fn (Blueprint $t) => $t->string('bank_account')->nullable()->change());
    }
};
