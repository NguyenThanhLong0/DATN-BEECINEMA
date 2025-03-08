<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RankController extends Controller
{
    //
    public function index()
    {
        try {
            $ranks = Rank::query()->get();
            return response()->json([
                'message' => 'Hiển thị thành công',
                'satus' => true,
                'data' => $ranks
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'lỗi hiển thị',
                'satus' => false,
            ]);
        }
    }
    public function show(Rank $rank)
    {
        try {
            return response()->json([
                'message' => 'Chi tiết thành công',
                'satus' => true,
                'data' => $rank
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'lỗi chi tiết',
                'satus' => false,
            ]);
        }
    }
    public function store(Request $request)
    {
        try {
            // Kiểm tra xem số lượng rank hiện tại có vượt quá 5 không
            $rankCount = Rank::count();
            if ($rankCount >= 5) {
                return response()->json([
                    'status' => false,
                    'message' => 'Số lượng cấp bậc đã đạt đến tối đa, không thể thêm mới.'
                ], 500);
            }
            $data = $request->validate([
                'name' => 'required|string|max:255|unique:ranks,name',
                'total_spent' => 'required|integer|min:0|unique:ranks,total_spent',
                'ticket_percentage' => 'required|integer|min:0|max:100',
                'combo_percentage' => 'required|integer|min:0|max:100',
                'is_default' => 'required|boolean',
            ], [
                'name.required' => 'Vui lòng nhập tên cấp bậc.',
                'name.string' => 'Tên cấp bậc phải là một chuỗi ký tự.',
                'name.max' => 'Tên cấp bậc không được vượt quá 255 ký tự.',
                'name.unique' => 'Tên cấp bậc đã tồn tại, vui lòng chọn tên khác.',
                'total_spent.required' => 'Vui lòng nhập tổng chi tiêu.',
                'total_spent.integer' => 'Tổng chi tiêu phải là một số nguyên.',
                'total_spent.min' => 'Tổng chi tiêu tối thiểu phải bằng 500.000 VNĐ.',
                'total_spent.max' => 'Tổng chi tiêu không được quá 5.000.000 VNĐ.',
                'total_spent.regex' => 'Tổng chi tiêu chia hết cho 100.000.',
                'total_spent.unique' => 'Tổng chi tiêu đã tồn tại cho cấp bậc khác',
                'ticket_percentage.required' => 'Vui lòng nhập phần trăm tích điểm vé.',
                'ticket_percentage.integer' => 'Phần trăm tích điểm vé phải là một số nguyên.',
                'ticket_percentage.min' => 'Phần trăm tích điểm vé tối thiểu phải bằng 0%',
                'ticket_percentage.max' => 'Phần trăm tích điểm vé không được quá 20%',
                'combo_percentage.required' => 'Vui lòng nhập phần trăm tích điểm combo.',
                'combo_percentage.integer' => 'Phần trăm tích điểm combo phải là một số nguyên.',
                'combo_percentage.min' => 'Phần trăm tích điểm combo tối thiểu phải bằng 0%.',
                'combo_percentage.max' => 'Phần trăm tích điểm combo không được vượt quá 20%.',
                'ticket_percentage.greater_than_previous' => 'Phần trăm tích điểm vé phải lớn hơn cấp bậc có tổng chi tiêu thấp hơn.',
                'combo_percentage.greater_than_previous' => 'Phần trăm tích điểm combo phải lớn hơn cấp bậc có tổng chi tiêu thấp hơn.',
            ]);
            // Tạo rank mới
            $rank = Rank::create($data);

            return response()->json([
                'message' => 'Rank được tạo thành công!',
                'data' => $rank
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi không mong muốn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function update(Request $request, Rank $rank)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:ranks,name,' . $rank->id,
                'total_spent' => 'required|integer|min:0|unique:ranks,total_spent,' . $rank->id,
                'ticket_percentage' => 'required|integer|min:0|max:100',
                'combo_percentage' => 'required|integer|min:0|max:100',
                'is_default' => 'required|boolean',
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'integer' => ':attribute phải là một số nguyên.',
                'min' => ':attribute phải có giá trị ít nhất là :min.',
                'max' => ':attribute không được vượt quá :max.',
                'unique' => ':attribute đã tồn tại trong hệ thống.',
            ], [
                'name' => 'Tên cấp bậc',
                'total_spent' => 'Tổng chi tiêu',
                'ticket_percentage' => 'Phần trăm tích điểm vé',
                'combo_percentage' => 'Phần trăm tích điểm combo',
                'is_default' => 'Trạng thái mặc định',
            ]);

            // Ghi log dữ liệu đầu vào sau khi xác thực
            Log::debug('Data before update', ['data' => $validated]);

            // Cập nhật dữ liệu cho rank hiện tại
            $rank->update([
                'name' => $validated['name'],
                'total_spent' => $validated['total_spent'],
                'ticket_percentage' => $validated['ticket_percentage'],
                'combo_percentage' => $validated['combo_percentage'],
                'is_default' => $validated['is_default'],
            ]);

            return response()->json([
                'message' => 'Cấp bậc đã được cập nhật thành công!',
                'data' => $rank
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi không mong muốn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Rank $rank)
    {
        try {
            if (Rank::count() <= 2) {
                return response()->json([
                    'message' => 'Số lượng cấp bậc đã đạt đến tối tiểu, không thể xóa'
                ]);
            }
            // Kiểm tra nếu $rank có is_default = true
            if ($rank->is_default) {
                return response()->json([
                    'message' => 'Không thể xóa cấp bậc mặc định'
                ]);
            }
            $rank->delete();
            return response()->json([
                'message' => 'Rank đã được xóa thành công!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa rank!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
