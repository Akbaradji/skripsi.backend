<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
// Pastikan ini diimpor jika Anda menggunakan HasApiTokens di model User
// use Laravel\Sanctum\HasApiTokens; // Ini harus di model User, bukan di controller

class AuthController extends Controller
{
    /**
     * Handle user registration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validasi input biasa + token captcha
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'g-recaptcha-response' => 'required',
        ]);

        // Verifikasi captcha
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json();

        // Log respons reCAPTCHA untuk debugging
        Log::info('reCAPTCHA Register Response: ' . json_encode($body));

        if (!isset($body['success']) || $body['success'] !== true) {
            // Jika ada error codes dari Google, bisa ditambahkan ke pesan
            $errorCodes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            return response()->json([
                'message' => 'Verifikasi Captcha gagal.',
                'errors' => ['g-recaptcha-response' => ["Verifikasi Captcha gagal. Kode error: {$errorCodes}"]]
            ], 422);
        }

        // Jika valid, lanjut proses pembuatan user
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')), // ⭐ Pastikan password di-hash saat register
            'role' => 'mahasiswa',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    /**
     * Handle user login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request) // ⭐ PASTIKAN FUNGSI INI ADA DAN NAMANYA BENAR
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'g-recaptcha-response' => 'required',
        ]);

        // Verifikasi captcha
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json();

        // Log respons reCAPTCHA untuk debugging
        Log::info('reCAPTCHA Login Response: ' . json_encode($body));

        if (!isset($body['success']) || $body['success'] !== true) {
            $errorCodes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            return response()->json([
                'message' => 'Verifikasi Captcha gagal.',
                'errors' => ['g-recaptcha-response' => ["Verifikasi Captcha gagal. Kode error: {$errorCodes}"]]
            ], 422);
        }

        // Coba login
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // ⭐ Pastikan user tidak null sebelum memanggil createToken
        if (!$user) {
            return response()->json(['message' => 'User not found after authentication.'], 500);
        }

        // ⭐ createToken() membutuhkan HasApiTokens trait di model User
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Log the user out (revoke current token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Hapus token otentikasi saat ini
        if ($request->user()) { // Tambahkan pengecekan null
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logout berhasil']);
    }

    /**
     * Get the authenticated user's details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        // ⭐ Pastikan user tidak null
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $userId = $user->id;

        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Update data user
        $user->name = $request->input('name');
        $user->email = $request->input('email');

        // Update password jika ada
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}
