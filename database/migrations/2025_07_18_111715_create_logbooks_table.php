<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Mengaitkan dengan user yang membuat logbook
            $table->date('tanggal'); // Tanggal logbook dibuat/dilakukan
            $table->text('aktivitas'); // Deskripsi aktivitas harian/mingguan
            $table->text('catatan_pembimbing')->nullable(); // Catatan dari pembimbing (opsional)
            $table->string('status')->default('pending'); // Status logbook (pending, disetujui, ditolak)
            $table->timestamps(); // created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbooks');
    }
};
