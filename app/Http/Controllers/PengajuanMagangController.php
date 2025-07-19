<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PengajuanMagangController extends Controller
{
    /**
     * Menampilkan daftar pengajuan magang.
     * Admin bisa melihat semua pengajuan.
     * Mahasiswa hanya bisa melihat pengajuan miliknya.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $pengajuan = PengajuanMagang::with('user')->orderBy('created_at', 'desc')->get();
        } else {
            $pengajuan = PengajuanMagang::with('user')
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json($pengajuan);
    }

    /**
     * Menampilkan detail pengajuan magang berdasarkan ID.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        /** @var \App\Models\PengajuanMagang $pengajuan */
        $pengajuan = PengajuanMagang::with('user')->findOrFail($id);

        // Jika mahasiswa, batasi akses hanya miliknya sendiri
        if ($request->user()->role !== 'admin' && $pengajuan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($pengajuan);
    }

    /**
     * Membuat pengajuan magang baru (hanya mahasiswa).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'bidang_magang' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'dokumen' => 'nullable|file|mimes:pdf|max:2048', // Hanya PDF, max 2MB (2048 KB)
        ], [
            'dokumen.mimes' => 'File dokumen harus berformat PDF.',
            'dokumen.max' => 'Ukuran file dokumen tidak boleh lebih dari 2MB.',
        ]);

        $user = $request->user();

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Hanya mahasiswa yang bisa membuat pengajuan'], 403);
        }

        /** @var \App\Models\PengajuanMagang $pengajuan */
        $pengajuan = new PengajuanMagang();
        $pengajuan->user_id = $user->id;

        // Sanitasi input untuk hindari XSS
        $pengajuan->bidang_magang = strip_tags($request->bidang_magang);
        $pengajuan->tanggal_mulai = $request->tanggal_mulai;
        $pengajuan->tanggal_selesai = $request->tanggal_selesai;
        $pengajuan->status = 'pending';

        if ($request->hasFile('dokumen')) {
            // Simpan ke disk public agar bisa diakses dan dihapus dengan aman
            $path = $request->file('dokumen')->store('dokumen_pengajuan', 'public');
            $pengajuan->dokumen = $path;
        }

        $pengajuan->save();

        return response()->json(['message' => 'Pengajuan berhasil dibuat', 'data' => $pengajuan], 201);
    }

    /**
     * Memperbarui status pengajuan (hanya admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        // Validasi status dan catatan
        $request->validate([
            'status' => 'required|in:pending,disetujui,ditolak',
            'catatan' => 'nullable|string|max:500',
        ]);

        // Cek apakah pengguna yang melakukan request adalah admin
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        /** @var \App\Models\PengajuanMagang $pengajuan */
        $pengajuan = PengajuanMagang::findOrFail($id);

        // Jika status disetujui, set tanggal diterima menjadi tanggal saat ini
        if ($request->status === 'disetujui') {
            $pengajuan->tanggal_diterima = \Carbon\Carbon::now();
        }

        // Update status dan catatan
        $pengajuan->status = $request->status;

        // Sanitasi catatan admin agar tidak menyisipkan HTML/JS
        $pengajuan->catatan = strip_tags($request->catatan ?? '');

        // Simpan perubahan ke database
        $pengajuan->save();

        return response()->json(['message' => 'Status pengajuan berhasil diperbarui', 'data' => $pengajuan]);
    }

    /**
     * Menghapus pengajuan (admin, atau mahasiswa jika statusnya pending).
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        /** @var \App\Models\PengajuanMagang $pengajuan */
        $pengajuan = PengajuanMagang::findOrFail($id);

        // Admin bisa hapus pengajuan apapun
        if ($request->user()->role === 'admin') {
            if ($pengajuan->dokumen && Storage::disk('public')->exists($pengajuan->dokumen)) {
                Storage::disk('public')->delete($pengajuan->dokumen);
            }
            // Hapus juga bukti selesai magang jika ada
            if ($pengajuan->bukti_selesai_path && Storage::disk('public')->exists($pengajuan->bukti_selesai_path)) {
                Storage::disk('public')->delete($pengajuan->bukti_selesai_path);
            }
            $pengajuan->delete();
            return response()->json(['message' => 'Pengajuan berhasil dihapus']);
        }

        // User hanya bisa hapus pengajuan miliknya sendiri dengan status pending
        if ($request->user()->role === 'mahasiswa') {
            if ($pengajuan->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($pengajuan->status !== 'pending') {
                return response()->json(['message' => 'Pengajuan yang sudah diproses tidak bisa dihapus'], 403);
            }

            // hapus dokumen jika ada
            if ($pengajuan->dokumen && Storage::disk('public')->exists($pengajuan->dokumen)) {
                Storage::disk('public')->delete($pengajuan->dokumen);
            }
            // Hapus juga bukti selesai magang jika ada
            if ($pengajuan->bukti_selesai_path && Storage::disk('public')->exists($pengajuan->bukti_selesai_path)) {
                Storage::disk('public')->delete($pengajuan->bukti_selesai_path);
            }

            $pengajuan->delete();
            return response()->json(['message' => 'Pengajuan berhasil dihapus']);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
