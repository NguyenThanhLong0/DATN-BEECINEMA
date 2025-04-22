<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserVoucher;
use App\Models\Voucher;

class CancelVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $voucherId;
    protected $orderCode;

    public function __construct($userId, $voucherId, $orderCode)
    {
        $this->userId = $userId;
        $this->voucherId = $voucherId;
        $this->orderCode = $orderCode;
    }

    public function handle()
    {
        DB::beginTransaction();
        try {
            // Xóa bản ghi user_voucher mới nhất của người dùng với voucher này
            $userVoucher = UserVoucher::where('user_id', $this->userId)
                ->where('voucher_id', $this->voucherId)
                ->whereNull('ticket_id') // Chỉ xóa nếu chưa liên kết với ticket
                ->orderBy('id', 'desc')
                ->first();

            if ($userVoucher) {
                $userVoucher->delete();

                // Giảm số used_count trong bảng voucher
                Voucher::where('id', $this->voucherId)->decrement('used_count');

                Log::info("Hủy voucher thành công: voucher_id={$this->voucherId}, user_id={$this->userId}");
            } else {
                Log::warning("Không tìm thấy user_voucher cần hủy: voucher_id={$this->voucherId}, user_id={$this->userId}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi khi hủy voucher: " . $e->getMessage());
        }
    }
}
