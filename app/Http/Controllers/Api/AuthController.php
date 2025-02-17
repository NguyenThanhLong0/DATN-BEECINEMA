<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;
use Exception;



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
    
            // Tìm user theo email
            $user = User::where('email', $request->email)->first();
    
            // Kiểm tra user có tồn tại và mật khẩu có đúng không
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            // Xóa token cũ nếu cần
            $user->tokens()->delete();
    
            // Tạo token mới
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
    
    
    // public function login(Request $request)
    // {
    //     try {
    //         $credentials = $request->only('email', 'password');

    //         if (!Auth::attempt($credentials)) {
    //             return response()->json(['error' => 'Unauthorized'], 401);
    //         }

    //         $user = Auth::user();
    //         $token = $user->createToken('authToken')->plainTextToken;

    //         return response()->json(['user' => $user, 'token' => $token], 200);
    //     } catch (Exception $e) {
    //         return response()->json(['error' => 'Login failed', 'message' => $e->getMessage()], 500);
    //     }
    // }

//     public function login(Request $request)
// {
//     try {
//         $credentials = $request->only('email', 'password');

//         if (!Auth::attempt($credentials)) {
//             return response()->json(['error' => 'Unauthorized'], 401);
//         }

//         $user = Auth::user();
//         $plainTextToken = Str::random(80);

//         // Tạo và lưu token thủ công bằng PersonalAccessToken
//         PersonalAccessToken::create([
//             'tokenable_type' => get_class($user),
//             'tokenable_id' => $user->id,
//             'name' => 'authToken',
//             'token' => hash('sha256', $plainTextToken),
//             'abilities' => ['*'],
//         ]);

//         return response()->json(['user' => $user, 'token' => $plainTextToken], 200);
//     } catch (Exception $e) {
//         return response()->json(['error' => 'Login failed', 'message' => $e->getMessage()], 500);
//     }
// }


// public function login(Request $request)
// {
//     try {
//         $credentials = $request->only('email', 'password');

//         if (!Auth::attempt($credentials)) {
//             return response()->json(['error' => 'Unauthorized'], 401);
//         }

//         $user = Auth::user();

//         // Debug: Kiểm tra xem người dùng có lấy được không
//         if (!$user) {
//             return response()->json(['error' => 'User not found'], 404);
//         }

//         // Kiểm tra xem User Model có HasApiTokens không
//         if (!in_array('Laravel\Sanctum\HasApiTokens', class_uses($user))) {
//             return response()->json(['error' => 'User model is missing HasApiTokens'], 500);
//         }

//         // Debug: Kiểm tra kết nối DB
//         try {
//             \DB::connection()->getPdo();
//         } catch (\Exception $e) {
//             return response()->json(['error' => 'Database connection failed', 'message' => $e->getMessage()], 500);
//         }

//         // Tạo token với Sanctum
//         $token = $user->createToken('authToken');

//         // Debug: Kiểm tra token có được tạo không
//         if (!$token) {
//             return response()->json(['error' => 'Token creation failed'], 500);
//         }

//         return response()->json([
//             'user' => $user,
//             'token' => $token->plainTextToken, // Sử dụng plainTextToken
//         ], 200);
//     } catch (\Exception $e) {
//         return response()->json([
//             'error' => 'Login failed',
//             'message' => $e->getMessage(),
//         ], 500);
//     }
// }

// public function login(Request $request)
// {
//     try {
//         $credentials = $request->only('email', 'password');

//         if (!Auth::attempt($credentials)) {
//             return response()->json(['error' => 'Unauthorized'], 401);
//         }

//         $user = Auth::user();
//         $plainTextToken = Str::random(80); // Tạo token ngẫu nhiên

//         // Sửa lỗi: Đảm bảo tokenable_type và tokenable_id có giá trị
//         PersonalAccessToken::create([
//             'tokenable_type' => get_class($user), // Chắc chắn truyền giá trị này
//             'tokenable_id' => $user->id, // Chắc chắn truyền giá trị này
//             'name' => 'authToken',
//             'token' => hash('sha256', $plainTextToken), // Mã hóa token SHA-256
//             'abilities' => json_encode(['*']), // Định dạng JSON chính xác
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);

//         return response()->json(['user' => $user, 'token' => $plainTextToken], 200);
//     } catch (Exception $e) {
//         return response()->json(['error' => 'Login failed', 'message' => $e->getMessage()], 500);
//     }
// }

}
