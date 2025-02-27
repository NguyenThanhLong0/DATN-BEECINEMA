<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PostApiController extends Controller
{
    /**
     * Lấy danh sách bài viết (GET /api/posts)
     */
    public function index()
    {
        try {
            $posts = Post::with('user:id,name,email,phone,gender,birthday,address')->orderBy('created_at', 'desc')->get();
            return response()->json($posts, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể lấy danh sách bài viết!', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tạo bài viết mới (POST /api/posts)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'title'       => 'required|string|max:255',
                'description' => 'required|string',
                'content'     => 'required|string',
                'img_post'    => 'nullable|url',
                'is_active'   => 'required|boolean', // Chỉ chấp nhận URL thay vì file ảnh
            ]);
            
            $post = Post::create([
                'user_id'     => Auth::id(), 
                'title'       => $request->title,
                'slug'        => Str::slug($request->title),
                'description' => $request->description,
                'content'     => $request->content,
                'img_post'    => $request->img_post, // Lưu trực tiếp URL của ảnh
                'is_active'   => $request->is_active,
            ]);
    
            return response()->json(['message' => 'Bài viết được tạo thành công!', 'post' => $post], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Dữ liệu không hợp lệ!', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể tạo bài viết!', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Xem chi tiết bài viết (GET /api/posts/{id})
     */
    public function show($id)
    {
        try {
            $post = Post::with('user:id,name,email,phone,gender,birthday,address')->findOrFail($id);

            if (!session()->has('viewed_post_' . $post->id)) {
                $post->increment('view_count');
                session()->put('viewed_post_' . $post->id, true);
            }

            return response()->json($post, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bài viết không tồn tại!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi lấy bài viết!', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật bài viết (PUT/PATCH /api/posts/{id})
     */
    public function update(Request $request, $id)
{
    try {
        $post = Post::findOrFail($id);

        // Xác thực dữ liệu
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'slug'        => 'nullable|string|max:255' . $id,
            'description' => 'sometimes|string',
            'content'     => 'sometimes|string',
            'img_post'    => 'nullable|url',
            'is_active'   => 'sometimes|boolean',
        ]);

        // Nếu có title mới, tạo slug mới

            $slug = Str::slug($validated['title']) ?? $post->slug;
        

        // Cập nhật bài viết
        $post->update([
            'title'       => $validated['title'] ?? $post->title,
            'slug'        => $slug,
            'description' => $validated['description'] ?? $post->description,
            'content'     => $validated['content'] ?? $post->content,
            'img_post'    => $validated['img_post'] ?? $post->img_post,
            'is_active'   => $validated['is_active'] ?? $post->is_active,
        ]);

        return response()->json(['message' => 'Bài viết đã được cập nhật!', 'post' => $post], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['error' => 'Bài viết không tồn tại!'], 404);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => 'Dữ liệu không hợp lệ!', 'messages' => $e->errors()], 422);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Lỗi khi cập nhật bài viết!', 'message' => $e->getMessage()], 500);
    }
}

    /**
     * Xóa bài viết (DELETE /api/posts/{id})
     */
    public function destroy($id)
    {
        try {
            $post = Post::findOrFail($id);
            $post->delete();
            return response()->json(['message' => 'Bài viết đã được xóa thành công!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bài viết không tồn tại!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi xóa bài viết!', 'message' => $e->getMessage()], 500);
        }
    }


}
