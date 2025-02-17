<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;


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
}
