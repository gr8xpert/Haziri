<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kiosk face recognition (feature kiosk_face): face descriptors per employee — numbers, not photos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('descriptor'); // 1024 floats from the face description model
            $table->float('quality')->nullable(); // detection score when enrolled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_templates');
    }
};
