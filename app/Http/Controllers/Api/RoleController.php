<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{
     // Hiển thị danh sách tất cả các role
     public function index()
     {
         // Lấy danh sách tất cả các role
         $roles = Role::select('id', 'name')->orderby('id')->get();
         return response()->json($roles);
     }
 
     /**
      * Store a newly created resource in storage.
      */
     public function store(Request $request)
     {
         $validated = $request->validate([
             'name' => 'required|string|unique:roles,name',
             'permissions' => 'array',
             'permissions.*' => 'exists:permissions,name',
         ]);
 
         // Tạo mới một role
         $role = Role::create([
             'name' => $validated['name'],
             'guard_name' => 'web'  // Hoặc dùng 'api' tùy thuộc vào guard của bạn
         ]);
 
         // Gán các permission cho role nếu có
         if (!empty($validated['permissions'])) {
             $permissions = Permission::whereIn('name', $validated['permissions'])->get();
             $role->givePermissionTo($permissions);
         }
 
         // Lấy danh sách permissions đã gán cho role
         $data = $role->toArray();
         $permissions = $role->permissions->pluck('name');
         $data['permissions'] = $permissions;
 
         return response()->json($data, 201); // Trả về mã 201 khi tạo thành công
     }
 
     /**
      * Display the specified resource.
      */
     public function show(string $id)
     {
         try {
             // Lấy thông tin role theo ID
             $role = Role::findOrFail($id);
 
             // Trả về thông tin role kèm với danh sách permissions
             $data = $role->toArray();
             $permissions = $role->permissions->pluck('name');
             $data['permissions'] = $permissions;
 
             return response()->json($data);
         } catch (ModelNotFoundException $e) {
             return response()->json(['error' => 'Role không tồn tại'], 404);
         } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage()], 500);
         }
     }
 
     /**
      * Update the specified resource in storage.
      */
     public function update(Request $request, string $id)
     {
         $role = Role::findOrFail($id);
 
         $validated = $request->validate([
             'name' => 'required|string|unique:roles,name,' . $id,
             'permissions' => 'array',
             'permissions.*' => 'exists:permissions,name',
         ]);
 
         // Cập nhật tên role
         $role->update([
             'name' => $validated['name'],
         ]);
 
         // Đồng bộ lại các permission cho role
         if (isset($validated['permissions'])) {
             $role->syncPermissions($validated['permissions']); // Xoá hết và gán lại permissions
         }
 
         // Lấy danh sách permissions đã gán cho role
         $data = $role->toArray();
         $permissions = $role->permissions->pluck('name');
         $data['permissions'] = $permissions;
 
         return response()->json($data);
     }
 
     /**
      * Remove the specified resource from storage.
      */
     public function destroy(string $id)
     {
         try {
             // Tìm role theo ID
             $role = Role::findOrFail($id);
 
             // Xóa role
             $role->delete();
 
             // Trả về thông báo thành công
             return response()->json([
                 'message' => 'Xóa role thành công'
             ], 200);
         } catch (ModelNotFoundException $e) {
             return response()->json([
                 'error' => 'Role không tồn tại'
             ], 404);
         } catch (Throwable $th) {
             return response()->json([
                 'error' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'
             ], 500);
         }
     }
}
