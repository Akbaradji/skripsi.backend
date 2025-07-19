<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            // Tambahkan kolom pengajuan_id
            $table->foreignId('pengajuan_id')->nullable()->constrained('pengajuan_magang')->onDelete('cascade');
            // onDelete('cascade') akan secara otomatis menghapus logbook jika pengajuan_magang terkait dihapus.
            // nullable() jika logbook bisa ada tanpa pengajuan (misal: logbook umum)
            // Hapus nullable() jika logbook WAJIB memiliki pengajuan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropForeign(['pengajuan_id']);
            $table->dropColumn('pengajuan_id');
        });
    }
};