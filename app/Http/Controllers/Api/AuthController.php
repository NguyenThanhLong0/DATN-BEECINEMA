<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Jobs\ResendVerificationEmailJob;
use App\Jobs\SendPasswordResetEmail;
use App\Models\Membership;
use App\Models\Rank;
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
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();
            
            $roles = $user->getRoleNames();
    
        
            return response()->json(['message' => 'Login successful'], 200);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $messages = [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là chuỗi ký tự.',
                'email' => ':attribute phải là địa chỉ email hợp lệ.',
                'max' => ':attribute không được dài quá :max ký tự.',
                'min' => ':attribute phải có ít nhất :min ký tự.',
                'confirmed' => ':attribute không khớp.',
                'unique' => ':attribute đã được sử dụng.',
                'regex' => ':attribute không hợp lệ.',
                'date' => ':attribute phải là ngày hợp lệ.',
                'in' => ':attribute phải là một trong các giá trị hợp lệ.',
            ];
    
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed|max:20',
                'phone' => ['required', 'regex:/^((0[2-9])|(84[2-9]))[0-9]{8}$/', 'unique:users,phone'],
                'gender' => 'required|string|in:nam,nữ,khác',
                'birthday' => 'required|date',
            ], $messages);
    
            if ($validator->fails()) {
                // Lấy tất cả lỗi và nối thành chuỗi
                $errorMessages = collect($validator->errors()->all())->implode(' ');
    
                return response()->json([
                    'message' => $errorMessages, // Trả về tất cả lỗi trong message
                    'errors' => $validator->errors() // Giữ lại errors để frontend xử lý nếu cần
                ], 422);
            }
    
            // Kiểm tra lại email trước khi tạo user (tránh race condition)
            if (User::where('email', $request->email)->exists()) {
                return response()->json([
                    'message' => 'Email đã được sử dụng.',
                    'errors' => ['email' => ['Email đã được sử dụng.']]
                ], 422);
            }
    
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthday' => $request->birthday,
            ]);
            $user->assignRole('member');
            
            event(new UserRegistered($user));
            // Gửi email xác thực
            $rank = Rank::where('is_default', true)->first();

            $date=Carbon::parse($user->birthday);
            $uniquePart = preg_replace('/\D/', '', microtime(true));
            $uniquePart = substr($uniquePart, -7); 
            $membership = [
                'user_id' => $user->id,
                'rank_id' => $rank->is_default,
                'code' => rand(1000000,9999999).$uniquePart, 
                'points' => 0,
                'total_spent' => 0,
            ];
            $user['role']=$user->getRoleNames()->implode(', ');
            $user = $user->makeHidden(['roles']); 

            Membership::create($membership);
    
            DB::commit();
    
            return response()->json([
                'message' => 'User registered successfully. Please verify your email.',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
    DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(), // Trả về lỗi server trong message
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function user(Request $request)
    {
        $user = Auth::user();

        // Lấy tất cả roles của user
        $roles = $user->getRoleNames();
    
        // Lấy tất cả permissions của user (qua role)
        $permissions = $user->getAllPermissions()->map(function ($permission) {
            return $permission->name; // Chỉ lấy tên permission
        });
    
        // Gộp thông tin vào chung một mảng
        $userData = $user->toArray(); // Chuyển toàn bộ thông tin user thành mảng
    
        // Thêm roles và permissions vào mảng userData
        $userData['roles'] = $roles;
        $userData['permissions'] = $permissions;
    
        // Trả về dữ liệu user với role và permission
        return response()->json([
            'user' => $userData
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully']);
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

    public function verifyEmail($id, $hash)
{
    // Tìm người dùng theo ID
    $user = User::find($id);

    if (!$user) {
        // Người dùng không tồn tại
        return redirect(config('app.frontend_url') . '/email-verified?status=error');
    }

    // Kiểm tra hash email
    if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
        // Hash không hợp lệ
        return redirect(config('app.frontend_url') . '/email-verified?status=invalid');
    }

    // Kiểm tra nếu email đã được xác minh
    if ($user->hasVerifiedEmail()) {
        // Email đã được xác minh
        return redirect(config('app.frontend_url') . '/email-verified?status=already-verified');
    }

    // Đánh dấu email là đã xác minh
    $user->markEmailAsVerified();

    // Chuyển hướng người dùng về frontend sau khi xác minh email thành công
    return redirect(config('app.frontend_url') . '/email-verified?status=success');
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


    public function handleGoogleCallback(Request $request)
    {
        try {
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
                $user->assignRole('member');
                
                $uniquePart = preg_replace('/\D/', '', microtime(true)); // Loại bỏ dấu chấm
                $uniquePart = substr($uniquePart, -7); 
                $membership = [
                    'user_id' => $user->id,
                    'rank_id' => $rank->is_default,
                    'code' => rand(1000000,9999999).$uniquePart, 
                    'points' => 0,
                    'total_spent' => 0,
                ];

                Membership::create($membership);
            } else {
                // Nếu user đã tồn tại nhưng chưa xác thực email, cập nhật email_verified_at
                $updateData = [];

                if (!$user->email_verified_at) {
                    $updateData['email_verified_at'] = Carbon::now();
                }

                // Nếu avatar thay đổi, cập nhật luôn
                if ($user->avatar !== $googleUser->getAvatar()) {
                    $updateData['avatar'] = $googleUser->getAvatar();
                }

                if (!empty($updateData)) {
                    $user->update($updateData);
                }
            }

            // Đăng nhập user vào hệ thống (dùng session)
            Auth::login($user);

            // Regenerate session để bảo mật
            $request->session()->regenerate();



            return response()->json(['message' => 'Login successful', 'user' => $user], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
