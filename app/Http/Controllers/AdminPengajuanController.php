<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Import kelas Log

class AdminPengajuanController extends Controller
{
    // API untuk statistik pengajuan
    public function stats()
    {
        try {
            return response()->json([
                'totalPengajuan' => PengajuanMagang::count(),
                'pending' => PengajuanMagang::where('status', 'pending')->count(),
                'disetujui' => PengajuanMagang::where('status', 'disetujui')->count(),
                'ditolak' => PengajuanMagang::where('status', 'ditolak')->count(),
            ]);
        } catch (\Exception $e) {
            // Tangkap exception dan kirim respons error yang lebih informatif
            return response()->json(['message' => 'Gagal mengambil statistik: ' . $e->getMessage()], 500);
        }
    }

    // API untuk daftar pengajuan (admin)
    public function index(Request $request)
    {
        try {
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
                    'nama_pengaju' => $item->user->name,
                    'email_pengaju' => $item->user->email,
                    'pdf_pengajuan' => $item->dokumen
                        ? url(Storage::url($item->dokumen))
                        : null,
                    'bukti_selesai_path' => $item->bukti_selesai_path
                        ? url(Storage::url($item->bukti_selesai_path))
                        : null,
                    'tanggal_diterima' => $item->tanggal_diterima ? Carbon::parse($item->tanggal_diterima)->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json($pengajuan);
        } catch (\Exception $e) {
             // Tangkap exception dan kirim respons error yang lebih informatif
            return response()->json(['message' => 'Gagal mengambil data: ' . $e->getMessage()], 500);
        }
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

    /**
     * ⭐ FUNGSI BARU: Mengunggah bukti selesai magang (hanya Admin).
     */
    public function uploadBuktiSelesai(Request $request)
    {
        // Log request untuk debugging
        Log::info('Upload Bukti Selesai Request:', $request->all());
        
        $request->validate([
            // Validasi ini memastikan pengajuan_id ada dan merupakan integer.
            // Validasi 'exists' dihilangkan untuk menghindari error DB, tetapi
            // findOrFail di bawah akan tetap mengecek keberadaan data.
            'pengajuan_id' => 'required|integer', 
            'bukti_selesai_file' => 'required|file|mimes:pdf|max:2048',
        ], [
            'bukti_selesai_file.mimes' => 'File bukti selesai harus berformat PDF.',
            'bukti_selesai_file.max' => 'Ukuran file tidak boleh lebih dari 2MB.',
        ]);

        try {
            // Cari pengajuan magang yang bersangkutan
            $pengajuan = PengajuanMagang::findOrFail($request->input('pengajuan_id'));
            
            Log::info('Pengajuan found:', ['id' => $pengajuan->id]);

            // Cek apakah ada file lama, jika ada, hapus
            if ($pengajuan->bukti_selesai_path && Storage::disk('public')->exists($pengajuan->bukti_selesai_path)) {
                Storage::disk('public')->delete($pengajuan->bukti_selesai_path);
                Log::info('Old file deleted:', ['path' => $pengajuan->bukti_selesai_path]);
            }

            // Simpan file yang baru diunggah
            $path = $request->file('bukti_selesai_file')->store('bukti_selesai', 'public');
            Log::info('New file stored:', ['path' => $path]);
            
            $pengajuan->bukti_selesai_path = $path;
            $pengajuan->save();

            return response()->json([
                'message' => 'Bukti selesai magang berhasil diunggah.',
                'data' => $pengajuan,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Pengajuan not found:', ['pengajuan_id' => $request->input('pengajuan_id')]);
            return response()->json(['message' => 'Pengajuan tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error('Error uploading proof:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengunggah bukti selesai magang.'], 500);
        }
    }
}