<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Validator;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\AuthError;

class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct()
    {
        $this->firebaseAuth = app('firebase.auth');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|min:10|max:15|unique:users', // Add phone field
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
            ]);

            $token = $user->createToken('LaravelAuthApp')->accessToken;

            return response()->json(['token' => $token], 200);
        } catch (\Exception $e) {
            Log::error('Error during registration: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred during registration.'], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Send Firebase 2FA code
            try {
                $this->firebaseAuth->sendVerificationCode($user->phone);
                return response()->json(['message' => '2FA code sent'], 200);
            } catch (AuthError $e) {
                Log::error('Firebase 2FA error: ' . $e->getMessage());
                return response()->json(['error' => '2FA code sending failed'], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function verify2fa(Request $request)
    {
        $request->validate([
            'verificationId' => 'required',
            'code' => 'required',
        ]);

        try {
            $verified = $this->firebaseAuth->verifyCode($request->verificationId, $request->code);

            if ($verified) {
                $user = Auth::user();
                $token = $user->createToken('LaravelAuthApp')->accessToken;

                return response()->json(['token' => $token], 200);
            } else {
                return response()->json(['error' => 'Invalid verification code'], 401);
            }
        } catch (AuthError $e) {
            Log::error('Firebase 2FA verification error: ' . $e->getMessage());
            return response()->json(['error' => '2FA verification failed'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->token()->revoke();
            return response()->json(['message' => 'Successfully logged out'], 200);
        } catch (\Exception $e) {
            Log::error('Error during logout: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred during logout.'], 500);
        }
    }
}
