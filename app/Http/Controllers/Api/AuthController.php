<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CustomerResource;
use App\Models\Umkm;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ];

        $user = User::create($userData);

        if ($request->role == 'umkm') {
            Umkm::create([
                'user_id' => $user->id,
                'nama_umkm' => $request->nama_umkm,
                'alamat' => $request->alamat,
                'telepon' => $request->telepon,
                'deskripsi' => $request->deskripsi,
                'kecamatan_id' => $request->kecamatan_id,
            ]);
        }

        if ($request->role == 'customer') {
            Customer::create([
                'user_id' => $user->id,
                'nama_customer' => $request->nama_customer,
                'alamat' => $request->alamat,
                'telepon' => $request->telepon,
                'kecamatan_id' => $request->kecamatan_id,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load(['umkm', 'customer'])),
            'message' => 'Register berhasil'
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        Log::debug('User found:', [
            'exists' => !is_null($user),
            'user_id' => $user->id ?? null,
            'hashed_password' => $user->password ?? null
        ]);

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load(['umkm', 'customer'])),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    public function user(Request $request)
    {
        return new UserResource($request->user()->load(['umkm', 'customer']));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'required_with:new_password',
            'new_password' => 'sometimes|min:8',
        ], [
            'current_password.required_with' => 'Password saat ini diperlukan untuk mengubah password',
            'new_password.min' => 'Password baru minimal 8 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Update name jika ada
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        // Update email jika ada
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        // Update password jika ada
        if ($request->has('new_password')) {
            // Verifikasi password saat ini
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Password saat ini salah'
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
        }

        $user->save();

        $user->refresh()->load(['umkm', 'customer']);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Profile berhasil diperbarui',
        ]);
    }
}
