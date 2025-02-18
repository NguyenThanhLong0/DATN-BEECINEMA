<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Jobs\ResendVerificationEmailJob;
use App\Jobs\SendPasswordResetEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);
    
            // Find user by email
            $user = User::where('email', $request->email)->first();
    
            // Check if user exists and password matches
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            // Delete old tokens if necessary
            $user->tokens()->delete();
    
            // Create a new token
            $token = $user->createToken('authToken')->plainTextToken;
    
            return response()->json([
                'user' => $user,
                'token' => $token
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Login failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'phone' => ['required', 'regex:/^((0[2-9])|(84[2-9]))[0-9]{8}$/'],
                'gender' => 'required|string|in:nam,nữ,khác',
                'birthday' => 'required|date',
            ]);
    
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthday' => $request->birthday,
            ]);
    
            event(new UserRegistered($user));

    
            return response()->json([
                'message' => 'User registered. Please verify your email.',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);
            SendPasswordResetEmail::dispatch($request->email);
            return response()->json(['message' => 'Password reset link sent.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Password reset failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'token' => 'required',
                'password' => 'required|string|min:8|confirmed',
            ]);
    
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->save();
    
                    event(new PasswordReset($user));
                }
            );
    
            return $status === Password::PASSWORD_RESET
                ? response()->json(['message' => 'Password reset successful.'])
                : response()->json(['message' => 'Invalid token or email.'], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Password reset failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request, $id, $hash)
{
    // Tìm user theo id
    $user = User::findOrFail($id);

    // Kiểm tra hash của email
    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link.'], 403);
    }

    // Kiểm tra nếu email đã được xác minh
    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified.']);
    }

    // Mark email as verified
    $user->markEmailAsVerified();

    return response()->json(['message' => 'Email verified successfully.']);
}


    public function resendVerificationEmail(Request $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email already verified']);
            }

            ResendVerificationEmailJob::dispatch($request->user());
            return response()->json(['message' => 'Verification email resent']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Resend verification email failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function changePassword(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Get authenticated user
        $user = $request->user();

        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Password change failed',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
