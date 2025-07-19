<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            // Tambahkan setelah kolom 'status' atau kolom relevan lainnya
            $table->string('bukti_selesai_path')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->dropColumn('bukti_selesai_path');
        });
    }
};
