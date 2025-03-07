<?php

namespace App\Jobs;

use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $userId;
    protected $voucherId;
    protected $discountAmount;

    public function __construct($userId, $voucherId, $discountAmount)
    {
        
        $this->userId = $userId;
        $this->voucherId = $voucherId;
        $this->discountAmount = $discountAmount;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Job ApplyVoucherJob chạy với userId={$this->userId}, voucherId={$this->voucherId}, discountAmount={$this->discountAmount}");
    
        // Kiểm tra user_id không bị null
        if (!$this->userId) {
            Log::error("LỖI: userId bị null khi chạy job!");
            return;
        }
    
        // Tạo bản ghi UserVoucher mới
        UserVoucher::create([
            'user_id' => $this->userId,
            'voucher_id' => $this->voucherId,
            'discount_applied' => $this->discountAmount
        ]);
    
        // Lấy voucher từ DB
        $voucher = Voucher::find($this->voucherId);
    
        if (!$voucher) {
            Log::error("LỖI: Không tìm thấy voucher ID={$this->voucherId}");
            return;
        }
    
        Log::info("Voucher trước khi tăng: ID={$this->voucherId}, used_count={$voucher->used_count}");
    
        // Kiểm tra nếu used_count bị null thì set về 0
        if ($voucher->used_count === null) {
            Log::warning("Voucher ID={$this->voucherId} có used_count bị NULL, đặt lại thành 0.");
            $voucher->used_count = 0;
        }
    
        // Tăng used_count
        $voucher->increment('used_count');
    
        // Kiểm tra lại sau khi cập nhật
        $voucher->refresh();
        Log::info("Voucher sau khi tăng: ID={$this->voucherId}, used_count={$voucher->used_count}");
    }
    

}
