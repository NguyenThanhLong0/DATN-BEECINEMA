<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
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
        $vouchers = Voucher::leftJoin('user_vouchers', function ($join) use ($userId) {
                $join->on('user_vouchers.voucher_id', '=', 'vouchers.id')
                     ->where('user_vouchers.user_id', '=', $userId);
            })
            ->where('vouchers.end_date', '>=', now()) // Chỉ lấy voucher chưa hết hạn
            ->where('vouchers.is_active', 1) // Chỉ lấy voucher đang hoạt động
            ->select(
                'vouchers.*',
                // Tổng số lần voucher đã được sử dụng trên toàn bộ hệ thống
                DB::raw('(SELECT COUNT(*) FROM user_vouchers uv WHERE uv.voucher_id = vouchers.id) as total_usage'),
                // Tổng số lượt sử dụng còn lại của voucher
                DB::raw('(vouchers.quantity - (SELECT COUNT(*) FROM user_vouchers uv WHERE uv.voucher_id = vouchers.id)) AS remaining_usage'),
                // Tổng số lần user hiện tại đã sử dụng voucher này
                DB::raw('(SELECT COUNT(*) FROM user_vouchers uv WHERE uv.voucher_id = vouchers.id AND uv.user_id = ' . $userId . ') as total_per_user')
            )
            ->havingRaw('total_usage < vouchers.quantity') // Kiểm tra tổng số lượt sử dụng
            ->havingRaw('total_per_user < vouchers.per_user_limit') // Kiểm tra số lượt sử dụng tối đa của user
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

    // Lấy membership kèm theo rank và lịch sử điểm
    $membership = Membership::with([
        'rank',
        'pointHistories' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }
    ])->where('user_id', $user->id)->first();

    if (!$membership) {
        return response()->json(['message' => 'Membership not found'], 404);

    }
    

    // Tính tổng điểm tích lũy và tổng điểm đã tiêu
    $totalEarnedPoints = $membership->pointHistories->where('type', 'Nhận điểm')->sum('points');
    $totalSpentPoints = $membership->pointHistories->where('type', 'Dùng điểm')->sum('points');

    // Thêm tổng điểm vào mảng membership
    $membership->totalEarnedPoints = $totalEarnedPoints;
    $membership->totalSpentPoints = $totalSpentPoints;

    return response()->json($membership);
}


}
