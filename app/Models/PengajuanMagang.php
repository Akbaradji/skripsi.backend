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

}
