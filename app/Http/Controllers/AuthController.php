<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'g-recaptcha-response' => 'required',
        ]);

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json();
        Log::info('reCAPTCHA Register Response: ' . json_encode($body));

        if (!isset($body['success']) || $body['success'] !== true) {
            $errorCodes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            return response()->json([
                'message' => 'Verifikasi Captcha gagal.',
                'errors' => ['g-recaptcha-response' => ["Verifikasi Captcha gagal. Kode error: {$errorCodes}"]]
            ], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
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
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'g-recaptcha-response' => 'required',
        ]);

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);
        
        $body = $response->json();
        Log::info('reCAPTCHA Login Response: ' . json_encode($body));

        if (!isset($body['success']) || $body['success'] !== true) {
            $errorCodes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            return response()->json([
                'message' => 'Verifikasi Captcha gagal.',
                'errors' => ['g-recaptcha-response' => ["Verifikasi Captcha gagal. Kode error: {$errorCodes}"]]
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not found after authentication.'], 500);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $user->role,
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
        if ($request->user()) {
            $request->user()->tokens()->delete();
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
}

