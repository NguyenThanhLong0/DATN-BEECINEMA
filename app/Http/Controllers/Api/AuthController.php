<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Jobs\ResendVerificationEmailJob;
use App\Jobs\SendPasswordResetEmail;
use App\Models\Membership;
use App\Models\Rank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

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
        DB::beginTransaction();
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


            $rank=Rank::where('is_default',true)->first();

            $membership=[
                'user_id' => $user->id,
                'rank_id'=>$rank->is_default,
                'code'=>str_pad($user->id, 12, '0', STR_PAD_LEFT),
                'points'=>0,
                'total_spent'=>0,
            ];

            Membership::create($membership);

            DB::commit();

            return response()->json([
                'message' => 'User registered. Please verify your email.',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
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

    //Đăng nhập bằng gg 
    public function redirectToGoogle()
    {
        try {
            $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    

    public function handleGoogleCallback()
{
    try {
        // Lấy thông tin người dùng từ Google
        $googleUser = Socialite::driver('google')->stateless()->user();

        // Kiểm tra xem user đã tồn tại chưa
        $user = User::where('email', $googleUser->getEmail())->first();

        // Nếu chưa có thì tạo mới
        $rank=Rank::where('is_default',true)->first();
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' =>$googleUser->getAvatar(),
                'password' => bcrypt('randompassword'), // Mật khẩu ngẫu nhiên
                'email_verified_at'=> Carbon::now()
            ]);
            Membership::create([
                'user_id' => $user->id,
                'rank_id'=>$rank->is_default,
                'code'=>str_pad($user->id, 12, '0', STR_PAD_LEFT),
                'points'=>0,
                'total_spent'=>0,
            ]);
        }

        // Tạo token đăng nhập
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
           'user' => $user,
           'token' => $token
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}
