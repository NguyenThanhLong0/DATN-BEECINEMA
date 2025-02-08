<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PostApiController extends Controller
{
    /**
     * Lấy danh sách bài viết (GET /api/posts)
     */
    public function index()
    {
        try {
            $posts = Post::orderBy('created_at', 'desc')->get();
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
                'img_post'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Xử lý upload ảnh
            $imagePath = $request->hasFile('img_post') ? $request->file('img_post')->store('posts', 'public') : null;

            $post = Post::create([
                'user_id'     => auth()->id() ?? 1, // Giả sử user_id = 1 nếu không đăng nhập
                'title'       => $request->title,
                'slug'        => Str::slug($request->title),
                'description' => $request->description,
                'content'     => $request->content,
                'img_post'    => $imagePath,
                'is_active'   => $request->has('is_active') ? 1 : 0,
            ]);

            return response()->json(['message' => 'Bài viết được tạo thành công!', 'post' => $post], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Dữ liệu không hợp lệ!', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['error' => 'Không thể tạo bài viết!', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Xem chi tiết bài viết (GET /api/posts/{id})
     */
    public function show($id)
    {
        try {
            $post = Post::findOrFail($id);

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

            $request->validate([
                'title'       => 'required|string|max:255',
                'slug'        => 'required|string|max:255|unique:posts,slug,' . $id,
                'description' => 'required|string',
                'content'     => 'required|string',
                'img_post'    => 'nullable|image|max:2048',
            ]);

            // Nếu có ảnh mới, xóa ảnh cũ
            if ($request->hasFile('img_post')) {
                if (!empty($post->img_post) && Storage::exists('public/' . $post->img_post)) {
                    Storage::delete('public/' . $post->img_post);
                }
                $post->img_post = $request->file('img_post')->store('posts', 'public');
            }

            // Cập nhật bài viết
            $post->update([
                'title'       => $request->title,
                'slug'        => Str::slug($request->title),
                'description' => $request->description,
                'content'     => $request->content,
                'img_post'    => $post->img_post,
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

            // Xóa ảnh nếu có
            if (!empty($post->img_post) && Storage::exists('public/' . $post->img_post)) {
                Storage::delete('public/' . $post->img_post);
            }

            $post->delete();
            return response()->json(['message' => 'Bài viết và ảnh đã được xóa thành công!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bài viết không tồn tại!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi xóa bài viết!', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bật / Tắt bài viết (PUT /api/posts/{id}/toggle)
     */
    public function toggle($id)
    {
        try {
            $post = Post::findOrFail($id);
            $post->is_active = !$post->is_active;
            $post->save();

            return response()->json(['message' => 'Trạng thái bài viết đã được cập nhật!', 'is_active' => $post->is_active], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Bài viết không tồn tại!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi cập nhật trạng thái bài viết!', 'message' => $e->getMessage()], 500);
        }
    }
}
