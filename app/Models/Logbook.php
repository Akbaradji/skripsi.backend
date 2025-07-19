<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal',
        'aktivitas',
        'status',
        'pengajuan_id', // ⭐ Pastikan ini ada di $fillable
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ⭐ Tambahkan relasi ke PengajuanMagang
    public function pengajuanMagang()
    {
        return $this->belongsTo(PengajuanMagang::class, 'pengajuan_id');
    }
}