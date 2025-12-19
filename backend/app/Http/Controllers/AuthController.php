<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserValidation;
use App\Models\User;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthController extends Controller
{

    public function register(UserValidation $request)
    {

        try {
            DB::beginTransaction();

            if ($request->email && User::where('email', $request->email)->exists()) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Email already registered'
                ], 409);
            }

            if ($request->role === 'admin' && Gate::denies('checkAdmin')) {
                DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to create admin role'
                ], 403);
            }


            $user = User::create($request->all());
            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Registration successful',
                'data' => ['user' => $user]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Registration failed'
            ], 500);
        }


    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string:min:6',
        ]);

        try {

            $credentials = $request->only('email', 'password');

            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No user found! Please register.'
                ], 404);
            }

            $user->tokens()->delete();

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $accessToken = $user->createToken('authToken')->accessToken;


            return response()->json([
                'status' => 1,
                'message' => 'Login successful',
                'data' => [
                    'user' => Auth::user(),
                    'access_token' => "Bearer-$accessToken"
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Login Failed: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Login failed'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {

            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No authenticated user found'
                ], 401);
            }

            $user()->token()->revoke();

            return response()->json([
                'status' => 1,
                'message' => 'Logout successful'
            ], 200);

        } catch (\Exception $e) {

            \Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Logout failed'
            ], 500);
        }
    }
}
