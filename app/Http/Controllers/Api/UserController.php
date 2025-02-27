<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{
     // Lấy danh sách user (chỉ admin mới có quyền)
     public function index()
     {
         try {
             $users = User::all();
             return response()->json($users, 201);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error fetching users', 'message' => $e->getMessage()], 500);
         }
     }
 
     // Lấy thông tin user cụ thể
     public function show($id)
     {
         try {
             $user = User::findOrFail($id);
             return response()->json($user, 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'User not found', 'message' => $e->getMessage()], 404);
         }
     }
 
     // Cập nhật user
     public function update(Request $request, $id)
     {
         try {
             $user = User::findOrFail($id);
             $user->update($request->all());
 
             return response()->json([
                 'message' => 'User updated successfully',
                 'user' => $user
             ], 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error updating user', 'message' => $e->getMessage()], 500);
         }
     }
 
     // Xóa mềm user (chỉ admin)
     public function destroy($id)
     {
         try {
             $user = User::findOrFail($id);
             $user->delete();
 
             return response()->json(['message' => 'User deleted successfully'], 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error deleting user', 'message' => $e->getMessage()], 500);
         }
     }
 
     // Khôi phục user đã bị xóa mềm
     public function restore($id)
     {
         try {
             $user = User::withTrashed()->findOrFail($id);
             $user->restore();
 
             return response()->json(['message' => 'User restored successfully'], 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error restoring user', 'message' => $e->getMessage()], 500);
         }
     }
 
     // Xóa vĩnh viễn user (chỉ admin)
     public function forceDelete($id)
     {
         try {
             $user = User::withTrashed()->findOrFail($id);
             $user->forceDelete();
 
             return response()->json(['message' => 'User permanently deleted'], 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error permanently deleting user', 'message' => $e->getMessage()], 500);
         }
     }
 
     // Lấy thông tin user đang đăng nhập
     public function profile()
     {
         try {
             return response()->json(Auth::user(), 200);
         } catch (Exception $e) {
             return response()->json(['error' => 'Error fetching profile', 'message' => $e->getMessage()], 500);
         }
     }
     public function getUserVouchers()
    {
    try {
        $userId = Auth::id(); // Lấy ID của user đăng nhập
    
        // Lấy danh sách voucher hợp lệ
        $vouchers = UserVoucher::join('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.id')
            ->where('user_vouchers.user_id', $userId)
            ->where('vouchers.end_date_time', '>=', now()) // Chỉ lấy voucher chưa hết hạn
            ->where('vouchers.is_active', 1) // Chỉ lấy voucher đang hoạt động
            ->whereColumn('user_vouchers.usage_count', '<', 'vouchers.limit') // So sánh trực tiếp giữa 2 bảng
            ->whereRaw('(SELECT SUM(uv.usage_count) FROM user_vouchers uv WHERE uv.voucher_id = user_vouchers.voucher_id) < vouchers.quantity') // Kiểm tra tổng usage_count // So sánh trực tiếp giữa 2 bảng
            ->select(
                'user_vouchers.*',
                'vouchers.code',
                'vouchers.title',
                'vouchers.description',
                'vouchers.discount',
                'vouchers.end_date_time',
                'vouchers.limit',
                DB::raw('(SELECT SUM(uv.usage_count) FROM user_vouchers uv WHERE uv.voucher_id = user_vouchers.voucher_id) as total_usage'), // Truy vấn con để lấy tổng lượt sử dụng
                DB::raw('(vouchers.quantity - COALESCE((SELECT SUM(uv.usage_count) FROM user_vouchers uv WHERE uv.voucher_id = user_vouchers.voucher_id), 0)) AS remaining_usage')
            )
            ->get();
    
        return response()->json($vouchers);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Error fetching vouchers',
            'message' => $e->getMessage()
        ], 500);
    }
    
    }
    public function membership()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::with([
            'membership.rank',  
            'membership.pointHistories' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }
        ])->find($user->id);

        return response()->json($user);
    }

}
