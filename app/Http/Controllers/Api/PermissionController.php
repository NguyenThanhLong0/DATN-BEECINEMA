<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::pluck('name');
        return response()->json($permissions);
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);
    
        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web'
        ]);
    
        // Gán permission này cho các role
        if (!empty($validated['roles'])) {
            $roles = Role::whereIn('name', $validated['roles'])->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }
        }
        $data=$permission->toArray();
    $roles=$permission->roles->pluck('name');
    $data['roles']=$roles;
    return response()->json($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $permission = Permission::findOrFail($id);
    
            $data=$permission->toArray();
    $roles=$permission->roles->pluck('name');
    $data['roles']=$roles;
    return response()->json($data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Permission không tồn tại'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $permission = Permission::findOrFail($id);

    $validated = $request->validate([
        'name' => 'required|string|unique:permissions,name,' . $id,
        'roles' => 'array',
        'roles.*' => 'exists:roles,name',
    ]);

    $permission->update([
        'name' => $validated['name'],
    ]);

    if (isset($validated['roles'])) {
        $permission->syncRoles($validated['roles']); // Xoá hết rồi gán lại
    }
    $data=$permission->toArray();
    $roles=$permission->roles->pluck('name');
    $data['roles']=$roles;
    return response()->json($data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
{
    try {
        // Tìm permission theo ID
        $permission = Permission::findOrFail($id);

        // Xóa permission
        $permission->delete();

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Xóa permission thành công'
        ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        // Trường hợp không tìm thấy permission
        return response()->json([
            'error' => 'Permission không tồn tại'
        ], 404);
    } catch (\Throwable $th) {
        // Xử lý các lỗi khác
        return response()->json([
            'error' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'
        ], 500);
    }
}


}
