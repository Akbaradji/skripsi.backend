<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPengajuanController extends Controller
{
    // API untuk statistik pengajuan
    public function stats()
    {
        return response()->json([
            'totalPengajuan' => PengajuanMagang::count(),
            'pending' => PengajuanMagang::where('status', 'pending')->count(),
            'disetujui' => PengajuanMagang::where('status', 'disetujui')->count(),
            'ditolak' => PengajuanMagang::where('status', 'ditolak')->count(),
        ]);
    }

    // Tambahan: API untuk daftar pengajuan (bisa kamu kembangkan nanti)
    public function index(Request $request)
    {
        $query = PengajuanMagang::with('user');

        // Filter berdasarkan status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan bidang magang
        if ($request->has('bidang_magang') && $request->bidang_magang !== '') {
            $query->where('bidang_magang', 'like', '%' . $request->bidang_magang . '%');
        }

        // Ambil data pengajuan dan petakan ke bentuk yang lebih jelas
        $pengajuan = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'bidang_magang' => $item->bidang_magang,
                'tanggal_mulai' => $item->tanggal_mulai,
                'tanggal_selesai' => $item->tanggal_selesai,
                'status' => $item->status,
                'catatan' => $item->catatan,
                'nama_pengaju' => $item->user->name,      // dari tabel users
                'email_pengaju' => $item->user->email,    // dari tabel users
                'pdf_pengajuan' => $item->dokumen
                    ? url(Storage::url($item->dokumen)) // ini akan menjadi: http://localhost:8000/storage/dokumen_pengajuan/xxx.pdf
                    : null,

            ];
        });

        return response()->json($pengajuan);
    }


    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'status' => 'required|in:disetujui,ditolak',
        ]);

        $pengajuan = PengajuanMagang::whereIn('id', $request->ids)->update([
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Status pengajuan berhasil diperbarui']);
    }
}
