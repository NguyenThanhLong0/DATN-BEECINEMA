<?php

namespace App\Console\Commands;

use App\Jobs\SendExpiringPointsNotification;
use App\Mail\PointsExpiring;
use App\Models\Membership;
use App\Models\PointHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyExpiringPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:notify-expiring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Lấy các điểm sắp hết hạn theo batch để tối ưu hiệu suất
            PointHistory::where('type', 'Nhận điểm')
                ->whereNotNull('expired_at')
                ->whereBetween('expired_at', [Carbon::now(), Carbon::now()->addDays(3)])
                ->chunkById(100, function ($expiringPoints) {
                    // Tải trước Membership và User liên quan để giảm truy vấn
                    $membershipIds = $expiringPoints->pluck('membership_id')->unique();
                    $memberships = Membership::whereIn('id', $membershipIds)
                        ->with('user') // Eager load User
                        ->get()
                        ->keyBy('id');

                    foreach ($expiringPoints as $point) {
                        $membership = $memberships->get($point->membership_id);
                        if ($membership && $membership->user) {
                            // Gửi thông báo với remaining_points
                            SendExpiringPointsNotification::dispatch(
                                $membership->user,
                                $point->remaining_points, // Sử dụng remaining_points
                                $point->expired_at
                            )->onQueue('emails');

                            Log::info('Dispatched expiring points notification', [
                                'user_id' => $membership->user_id,
                                'membership_id' => $point->membership_id,
                                'points' => $point->remaining_points,
                                'expired_at' => $point->expired_at,
                            ]);
                        } else {
                            Log::warning('Membership or User not found for expiring points', [
                                'point_id' => $point->id,
                                'membership_id' => $point->membership_id,
                            ]);
                        }
                    }
                });

            $this->info('Expiring points notifications sent successfully.');
        } catch (\Exception $e) {
            Log::error('Error sending expiring points notifications', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('An error occurred while sending expiring points notifications.');
        }
    }
}
