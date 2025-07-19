<?php

namespace App\Http\Controllers;

use App\Models\Logbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Untuk mendapatkan user yang sedang login
use Illuminate\Support\Carbon; // Untuk bekerja dengan tanggal
use App\Models\PengajuanMagang; // ⭐ Impor model PengajuanMagang

class LogbookController extends Controller
{
    /**
     * Menampilkan daftar logbook.
     * Admin bisa melihat semua logbook.
     * Mahasiswa hanya bisa melihat logbook miliknya.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Logbook::with('user');

        if ($user->role === 'mahasiswa') {
            $query->where('user_id', $user->id);
        }

        // Filter berdasarkan tanggal (opsional)
        if ($request->has('date')) {
            $query->whereDate('tanggal', $request->date);
        }

        // Filter berdasarkan status (opsional)
        if ($request->has('status') && in_array($request->status, ['pending', 'disetujui', 'ditolak'])) {
            $query->where('status', $request->status);
        }

        $logbooks = $query->orderBy('tanggal', 'desc')->get();

        return response()->json($logbooks);
    }

    /**
     * Menyimpan logbook baru (hanya mahasiswa).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Hanya mahasiswa yang bisa membuat logbook.'], 403);
        }

        $request->validate([
            'tanggal' => 'required|date|before_or_equal:today', // Tanggal tidak boleh di masa depan
            'aktivitas' => 'required|string|min:10', // Minimal 10 karakter
        ]);

        // ⭐ PERBAIKAN: Cari pengajuan magang yang aktif dan disetujui untuk user ini
        // Logika ini sangat penting untuk menemukan pengajuan_id yang benar
        $activePengajuan = PengajuanMagang::where('user_id', $user->id)
                                        ->where('status', 'disetujui')
                                        ->whereDate('tanggal_mulai', '<=', $request->tanggal)
                                        ->whereDate('tanggal_selesai', '>=', $request->tanggal)
                                        ->first();

        if (!$activePengajuan) {
            return response()->json(['message' => 'Anda tidak memiliki pengajuan magang aktif yang disetujui untuk tanggal ini.'], 400);
        }

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'pengajuan_id' => $activePengajuan->id, // ⭐ Set pengajuan_id di sini
            'tanggal' => $request->tanggal,
            'aktivitas' => strip_tags($request->aktivitas), // Sanitasi input aktivitas
            'status' => 'pending', // Default status saat dibuat
        ]);

        return response()->json(['message' => 'Logbook berhasil dibuat.', 'data' => $logbook], 201);
    }

    /**
     * Menampilkan detail logbook.
     * Admin bisa melihat semua. Mahasiswa hanya miliknya.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $logbook = Logbook::with('user')->findOrFail($id);

        if ($user->role === 'mahasiswa' && $logbook->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($logbook);
    }

    /**
     * Memperbarui logbook (hanya mahasiswa yang bisa update logbook miliknya sendiri, dan hanya jika statusnya pending).
     * Admin bisa mengubah status dan catatan pembimbing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $logbook = Logbook::findOrFail($id);

        // Validasi umum
        $request->validate([
            'aktivitas' => 'sometimes|required|string|min:10',
            'tanggal' => 'sometimes|required|date|before_or_equal:today',
            'status' => 'sometimes|required|in:pending,disetujui,ditolak',
            'catatan_pembimbing' => 'nullable|string|max:500',
        ]);

        if ($user->role === 'mahasiswa') {
            // Mahasiswa hanya bisa update logbook miliknya sendiri
            if ($logbook->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            // Mahasiswa hanya bisa update jika statusnya masih pending
            if ($logbook->status !== 'pending') {
                return response()->json(['message' => 'Logbook tidak bisa diubah karena sudah diproses.'], 403);
            }

            // Mahasiswa hanya bisa mengubah aktivitas dan tanggal
            $logbook->tanggal = $request->input('tanggal', $logbook->tanggal);
            $logbook->aktivitas = strip_tags($request->input('aktivitas', $logbook->aktivitas)); // Sanitasi
        } elseif ($user->role === 'admin') {
            // Admin bisa mengubah status dan catatan pembimbing
            $logbook->status = $request->input('status', $logbook->status);
            $logbook->catatan_pembimbing = strip_tags($request->input('catatan_pembimbing', $logbook->catatan_pembimbing)); // Sanitasi
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $logbook->save();

        return response()->json(['message' => 'Logbook berhasil diperbarui.', 'data' => $logbook]);
    }

    /**
     * Menghapus logbook (hanya admin, atau mahasiswa jika statusnya pending).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $logbook = Logbook::findOrFail($id);

        if ($user->role === 'admin') {
            $logbook->delete();
            return response()->json(['message' => 'Logbook berhasil dihapus oleh Admin.']);
        } elseif ($user->role === 'mahasiswa') {
            if ($logbook->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            if ($logbook->status !== 'pending') {
                return response()->json(['message' => 'Logbook tidak bisa dihapus karena sudah diproses.'], 403);
            }
            $logbook->delete();
            return response()->json(['message' => 'Logbook berhasil dihapus.']);
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
    }
}
