<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\PointHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:expire-points';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and expire points that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Lấy các điểm hết hạn theo batch để tối ưu hiệu suất
            PointHistory::where('type', 'Nhận điểm')
                ->whereNotNull('expired_at')
                ->where('expired_at', '<', Carbon::now())
                ->chunkById(100, function ($expiredPoints) {
                    // Tải trước các Membership liên quan để giảm truy vấn
                    $membershipIds = $expiredPoints->pluck('membership_id')->unique();
                    $memberships = Membership::whereIn('id', $membershipIds)->get()->keyBy('id');

                    foreach ($expiredPoints as $point) {
                        $membership = $memberships->get($point->membership_id);
                        if ($membership) {
                            // Trừ điểm hết hạn dựa trên remaining_points
                            $membership->points -= $point->remaining_points;
                            $membership->points = max(0, $membership->points); // Đảm bảo không âm
                            $membership->save();

                            // Tạo bản ghi lịch sử hết hạn
                            PointHistory::create([
                                'membership_id' => $point->membership_id,
                                'points' => -$point->remaining_points, // Giá trị ban đầu
                                'remaining_points' => -$point->remaining_points, // Để nhất quán
                                'type' => 'Hết hạn',
                                'created_at' => now(),
                            ]);

                            // Cập nhật bản ghi cũ
                            $point->type = 'Hết hạn';
                            $point->remaining_points = 0; // Đặt remaining_points về 0
                            $point->save();

                            Log::info('Processed expired points', [
                                'membership_id' => $point->membership_id,
                                'points' => $point->remaining_points,
                            ]);
                        } else {
                            Log::warning('Membership not found for point history', [
                                'point_id' => $point->id,
                                'membership_id' => $point->membership_id,
                            ]);
                        }
                    }
                });

            $this->info('Expired points have been processed successfully.');
        } catch (\Exception $e) {
            Log::error('Error processing expired points', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('An error occurred while processing expired points.');
        }
    }
}