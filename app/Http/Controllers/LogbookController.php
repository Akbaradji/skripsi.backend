<?php

namespace App\Http\Controllers;

use App\Models\Logbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\PengajuanMagang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // ⭐ Tambahkan import kelas Log

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

        if ($request->has('date')) {
            $query->whereDate('tanggal', $request->date);
        }

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
            'tanggal' => 'required|date|before_or_equal:today',
            'aktivitas' => 'required|string|min:10',
        ]);

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
            'pengajuan_id' => $activePengajuan->id,
            'tanggal' => $request->tanggal,
            'aktivitas' => strip_tags($request->aktivitas),
            'status' => 'pending',
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

        $request->validate([
            'aktivitas' => 'sometimes|required|string|min:10',
            'tanggal' => 'sometimes|required|date|before_or_equal:today',
            'status' => 'sometimes|required|in:pending,disetujui,ditolak',
            'catatan_pembimbing' => 'nullable|string|max:500',
        ]);

        if ($user->role === 'mahasiswa') {
            if ($logbook->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            if ($logbook->status !== 'pending') {
                return response()->json(['message' => 'Logbook tidak bisa diubah karena sudah diproses.'], 403);
            }

            $logbook->tanggal = $request->input('tanggal', $logbook->tanggal);
            $logbook->aktivitas = strip_tags($request->input('aktivitas', $logbook->aktivitas));
        } elseif ($user->role === 'admin') {
            $logbook->status = $request->input('status', $logbook->status);
            $logbook->catatan_pembimbing = strip_tags($request->input('catatan_pembimbing', $logbook->catatan_pembimbing));
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $logbook->save();

        return response()->json(['message' => 'Logbook berhasil diperbarui.', 'data' => $logbook]);
    }
    
    // ⭐ FUNGSI BARU: Mengubah status logbook (khusus admin)
    public function updateStatus(Request $request, $id)
    {
        Log::info('Logbook update status request received:', ['id' => $id, 'data' => $request->all()]);

        try {
            // Validasi permintaan
            $request->validate([
                'status' => 'required|in:disetujui,ditolak',
            ]);

            $user = Auth::user();

            // Cek apakah user adalah admin
            if ($user->role !== 'admin') {
                Log::warning('Unauthorized logbook status update attempt:', ['user_id' => $user->id]);
                return response()->json(['message' => 'Unauthorized. Hanya admin yang bisa mengubah status logbook.'], 403);
            }

            $logbook = Logbook::findOrFail($id);

            // Update status logbook
            $logbook->status = $request->input('status');
            $logbook->save();

            Log::info('Logbook status updated successfully:', ['id' => $logbook->id, 'new_status' => $logbook->status]);
            return response()->json(['message' => 'Status logbook berhasil diperbarui.', 'data' => $logbook]);
        } catch (ValidationException $e) {
            Log::error('Validation failed for logbook status update:', ['errors' => $e->errors()]);
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating logbook status:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal memperbarui status logbook.'], 500);
        }
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