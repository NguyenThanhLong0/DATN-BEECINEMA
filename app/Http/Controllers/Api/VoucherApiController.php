<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Facades\DB;

class VoucherApiController extends Controller
{
    public function index()
    {
        try {
            $vouchers = Voucher::withCount([
                'users as total_usage' => function ($query) {
                    $query->select(DB::raw('SUM(user_vouchers.usage_count)'));
                }
            ])->get();
            return response()->json($vouchers, Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:255|unique:vouchers,code',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date_time' => 'nullable|date',
                'end_date_time' => 'nullable|date|after_or_equal:start_date_time',
                'discount' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:1',
                'limit' => 'nullable|integer|min:1',
            ]);

            // Gán giá trị mặc định nếu không nhập ngày
            $validated['start_date_time'] = $validated['start_date_time'] ?? Carbon::now();
            $validated['end_date_time'] = $validated['end_date_time'] ?? Carbon::now()->addDays(7);
            $validated['type'] = $request->has('type') ? (bool) $request->type : false;

            // Không cần kiểm tra is_active vì MySQL đã xử lý bằng trigger
            $voucher = Voucher::create($validated);

            // Gán voucher cho tất cả các user có trong hệ thống
            $users = User::where('role','member')->get();
            $userVouchers = [];
            foreach ($users as $user) {
                $userVouchers[] = [
                    'user_id' => $user->id,
                    'voucher_id' => $voucher->id,
                    'usage_count' => 0,
                ];
            }
            UserVoucher::insert($userVouchers);

            // // Chèn dữ liệu vào bảng UserVoucher
            // if ($voucher['is_active']==true) {
            // }

            // Commit transaction nếu không có lỗi
            DB::commit();
            return response()->json($voucher, Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show($id)
    {
        try {
            $voucher = Voucher::withCount([
                'users as total_usage' => function ($query) {
                    $query->select(DB::raw('SUM(user_vouchers.usage_count)'));
                }
            ])->find($id);
            return response()->json($voucher, Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Voucher not found'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $validated = $request->validate([
                'code' => 'sometimes|string|max:255|unique:vouchers,code,' . $id,
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date_time' => 'nullable|date',
                'end_date_time' => 'nullable|date|after_or_equal:start_date_time',
                'discount' => 'sometimes|numeric|min:0',
                'quantity' => 'sometimes|integer|min:1',
                'limit' => 'nullable|integer|min:1',
            ]);
            if($request->start_date_time==null){
                $validated['start_date_time']=$voucher->start_date_time;
            }
            if($request->end_date_time==null){
                $validated['end_date_time']=$voucher->end_date_time;
            }

            $voucher->update($validated);

            return response()->json($voucher, Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Voucher not found'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy($id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $voucher->delete();
            return response()->json(['message' => 'Voucher deleted successfully'], Response::HTTP_NO_CONTENT);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Voucher not found'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



}
