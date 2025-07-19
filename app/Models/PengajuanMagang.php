<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanMagang extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_magang';

    protected $fillable = [
        'user_id',
        'bidang_magang',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'catatan',
        'dokumen',
        'bukti_selesai_path',
        'tanggal_diterima',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_diterima' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setCatatanAttribute($value)
    {
        $this->attributes['catatan'] = strip_tags($value);
    }

    public function setBidangMagangAttribute($value)
    {
        $this->attributes['bidang_magang'] = strip_tags($value);
    }

     // ⭐ Tambahkan relasi ke Logbook (satu pengajuan punya banyak logbook)
    public function logbooks()
    {
        return $this->hasMany(Logbook::class, 'pengajuan_id');
    }

    // Model event untuk menghapus logbook terkait saat pengajuan dihapus
    // Ini akan berfungsi jika onDelete('cascade') di migrasi tidak digunakan atau sebagai fallback
    protected static function booted()
    {
        static::deleting(function ($pengajuan) {
            // Hapus semua logbook yang terkait dengan pengajuan ini
            $pengajuan->logbooks()->delete();
        });
    }

}