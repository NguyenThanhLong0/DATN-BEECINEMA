<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyVoucherJob;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoucherApiController extends Controller
{
    public function index()
    {
        try {
            $vouchers = Voucher::all();

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
                'code'              => 'required|string|max:50|unique:vouchers,code',
                'title'             => 'required|string|max:255',
                'description'       => 'nullable|string',
                'start_date'        => 'nullable|date',
                'end_date'          => 'nullable|date|after_or_equal:start_date',
                'discount_type'     => 'required|in:fixed,percent',
                'discount_value'    => 'required|numeric|min:0',
                'min_order_amount'  => 'required|numeric|min:0',
                'max_discount_amount' => 'nullable|numeric|min:0',
                'is_active'         => 'required|boolean',
                'quantity'          => 'required|integer|min:1',
                'per_user_limit'    => 'nullable|integer|min:1',
            ]);

            // Gán giá trị mặc định nếu không nhập ngày
            $validated['start_date'] = $validated['start_date'] ?? Carbon::now();
            $validated['end_date'] = $validated['end_date'] ?? Carbon::now()->addDays(7);

            $voucher = Voucher::create($validated);

            // Gán voucher cho tất cả các user có role 'member'
            // $users = User::where('role', 'member')->get();
            // $userVouchers = [];
            // foreach ($users as $user) {
            //     $userVouchers[] = [
            //         'user_id'    => $user->id,
            //         'voucher_id' => $voucher->id,
            //     ];
            // }
            // UserVoucher::insert($userVouchers);

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
            $voucher = Voucher::all();

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
                'code'              => 'sometimes|string|max:50|unique:vouchers,code,' . $id,
                'title'             => 'sometimes|string|max:255',
                'description'       => 'nullable|string',
                'start_date'        => 'nullable|date',
                'end_date'          => 'nullable|date|after_or_equal:start_date',
                'discount_type'     => 'sometimes|in:fixed,percent',
                'discount_value'    => 'sometimes|numeric|min:0',
                'min_order_amount'  => 'sometimes|numeric|min:0',
                'max_discount_amount' => 'nullable|numeric|min:0',
                'quantity'          => 'sometimes|integer|min:1',
                'is_active'         => 'nullable|boolean',
                'per_user_limit'    => 'nullable|integer|min:1',
            ]);

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

    public function applyVoucher(Request $request)
    {
        $userId = Auth::id();
        $voucherCode = $request->voucher_code;
        $totalAmount= $request->total_amount;
        // Lấy thông tin voucher
        $voucher = Voucher::where('code', $voucherCode)->first();
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Voucher không tồn tại'], 400);
        }
        log::info($totalAmount);
    
        // Kiểm tra điều kiện sử dụng voucher
        if ($voucher->start_date > now()) {
            return response()->json(['success' => false, 'message' => 'Voucher chưa đến ngày sử dụng'], 400);
        }
        if ($voucher->end_date < now()) {
            return response()->json(['success' => false, 'message' => 'Voucher đã hết hạn'], 400);
        }
        if ($totalAmount < $voucher->min_order_amount) {
            return response()->json(['success' => false, 'message' => 'Chưa đủ điều kiện sử dụng voucher'], 400);
        }
        if ($voucher->used_count >= $voucher->quantity) {
            return response()->json(['success' => false, 'message' => 'Voucher đã hết lượt sử dụng'], 400);
        }
        if ($voucher->is_active == false) {
            return response()->json(['success' => false, 'message' => 'Voucher không còn hoạt động'], 400);
        }
    
        // Kiểm tra xem user đã áp dụng voucher nào chưa
        
    
        // Tính số tiền giảm giá
        $discountAmount = 0;
        if ($voucher->discount_type == 'fixed') {
            $discountAmount = $voucher->discount_value;
        } elseif ($voucher->discount_type == 'percent') {
            $discountAmount = ($voucher->discount_value / 100) * $totalAmount;
        }
    
        $newTotalAmount = $totalAmount - $discountAmount;
    
        // Lưu voucher vào database với số tiền giảm
        // Log::info("Dispatching ApplyVoucherJob: userId={$userId}, voucherId={$voucher->id}, discountAmount={$discountAmount}");
        ApplyVoucherJob::dispatch($userId, $voucher->id, $discountAmount);
    
        return response()->json([
            'success' => true,
            'message' => 'Voucher đã được áp dụng',
            'discounted_amount' => $newTotalAmount,
            'discount_value'=>$discountAmount,
        ]);
    }

    public function removeVoucher(Request $request){
        $userId = Auth::id();
       $voucherCode = $request->voucher_code;
       $totalAmount= $request->total_amount;
       // Lấy thông tin voucher
       $voucher = Voucher::where('code', $voucherCode)->first();
       if (!$voucher) {
           return response()->json(['success' => false, 'message' => 'Voucher không tồn tại'], 400);
       }
       $userVoucher = UserVoucher::where('user_id', $userId)->orderBy('id', 'desc')->first();
   
       if ($userVoucher) {
           // Nếu đang chọn cùng voucher thì gỡ bỏ
           if ($userVoucher->voucher_id == $voucher->id) {
               // Lấy voucher từ database
               $discount_applied=$userVoucher->discount_applied;
               $totalAmount=$totalAmount+$discount_applied;
               $voucherToUpdate = Voucher::find($voucher->id);
           
               if ($voucherToUpdate) {
                   $voucherToUpdate->update([
                       'used_count' => $voucherToUpdate->used_count - 1, // giảm số lần sử dụng
                   ]);
               }
       
               $userVoucher->delete();
       
               return response()->json([
                   'success' => true,
                   'message' => 'Voucher đã được gỡ bỏ',
                   'discounted_amount' => $totalAmount,
                   'discount_applied' => $discount_applied,
               ],200,[],JSON_NUMERIC_CHECK);
           } else {
               // Nếu user đã có voucher khác, xóa đi trước khi áp voucher mới
               $userVoucher->delete();
           }
       }else{
           return response()->json('mess:Không tìm thấy bản ghi');
       }
   }
}
