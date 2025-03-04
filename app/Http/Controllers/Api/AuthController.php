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

        // Kiểm tra đăng nhập
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Lấy user sau khi đăng nhập thành công
        $user = Auth::user();

        // Xóa token cũ nếu cần
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        // Lưu token vào cookie (24 giờ)
        $cookie = cookie('auth_token', $token, 1, '/', null, true, true);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user
        ])->cookie($cookie);
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
            // Xóa token nếu dùng Sanctum
            $request->user()->tokens()->delete();
    
            // Xóa session
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
    
            // Xóa cookie trên trình duyệt
            return response()->json(['message' => 'Successfully logged out'], 200)
                             ->withCookie(cookie()->forget('laravel_session'))
                             ->withCookie(cookie()->forget('XSRF-TOKEN'));
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
            if (!request()->has('code')) {
                return response()->json(['error' => 'Missing Google auth code'], 400);
            }
            // Lấy thông tin người dùng từ Google
            $googleUser = Socialite::driver('google')->stateless()->user();
    
            // Kiểm tra xem user đã tồn tại chưa
            $user = User::where('email', $googleUser->getEmail())->first();
    
            // Nếu chưa có thì tạo mới
            $rank = Rank::where('is_default', true)->first();
            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => bcrypt('randompassword'), // Mật khẩu ngẫu nhiên
                    'email_verified_at' => Carbon::now()
                ]);
    
                Membership::create([
                    'user_id' => $user->id,
                    'rank_id' => $rank->id,
                    'code' => str_pad($user->id, 12, '0', STR_PAD_LEFT),
                    'points' => 0,
                    'total_spent' => 0,
                ]);
            }
    
            // Đăng nhập user
            Auth::login($user);
            request()->session()->regenerate(); // Tạo session mới
    
            // Tạo token với Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
    
            // Lưu token vào cookie (24 giờ)
            $cookie = cookie('auth_token', $token, 1440, '/', null, true, true);
    
            return response()->json([
                'message' => 'Đăng nhập thành công',
                'user' => $user
            ])->cookie($cookie);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
