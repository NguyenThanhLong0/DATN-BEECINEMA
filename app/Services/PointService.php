<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\PointHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PointService
{
    /**
     * Lấy số điểm còn hiệu lực của một membership.
     *
     * @param int $membershipId
     * @return int
     */
    public function getAvailablePoints($membershipId)
    {
        return PointHistory::where('membership_id', $membershipId)
            ->where('type', 'Nhận điểm')
            ->where('expired_at', '>', Carbon::now())
            ->sum('remaining_points'); // Dùng remaining_points
    }

    /**
     * Tích điểm mới cho membership.
     *
     * @param int $membershipId
     * @param int $points
     * @param int|null $ticketId
     * @return void
     * @throws \Exception
     */
    public function earnPoints($membershipId, $points, $ticketId = null)
    {
        $membership = Membership::findOrFail($membershipId);

        // Ghi lịch sử tích điểm
        PointHistory::create([
            'membership_id' => $membershipId,
            'points' => $points,
            'remaining_points' => $points,
            'type' => 'Nhận điểm',
            'ticket_id' => $ticketId,
            'expired_at' => Carbon::now()->addDays(30), // Hết hạn sau 30 ngày
            'created_at' => now(),
        ]);

        // Cập nhật số dư điểm
        $membership->increment('points', $points);
    }

    /**
     * Sử dụng điểm để đặt vé.
     *
     * @param int $membershipId
     * @param int $pointsToUse
     * @param int|null $ticketId
     * @return array
     * @throws \Exception
     */
    public function usePoints($membershipId, $pointsToUse, $ticketId = null)
{
    $membership = Membership::findOrFail($membershipId);

    // Lấy các điểm còn hiệu lực (FIFO)
    $availablePoints = PointHistory::where('membership_id', $membershipId)
        ->where('type', 'Nhận điểm')
        ->where('expired_at', '>', Carbon::now())
        ->orderBy('expired_at', 'asc')
        ->get();

    // Tính tổng điểm còn hiệu lực
    $totalAvailablePoints = $availablePoints->sum('remaining_points');

    // Kiểm tra đủ điểm
    if ($totalAvailablePoints < $pointsToUse) {
        throw new \Exception("Không đủ điểm để sử dụng: Cần $pointsToUse điểm, nhưng chỉ có $totalAvailablePoints điểm hợp lệ.");
    }

    // Sử dụng điểm theo FIFO
    $remainingPointsToUse = $pointsToUse;
    foreach ($availablePoints as $point) {
        if ($remainingPointsToUse <= 0) break;

        $pointsToDeduct = min($point->remaining_points, $remainingPointsToUse);
        $remainingPointsToUse -= $pointsToDeduct;

        // Cập nhật bản ghi điểm
        $point->remaining_points -= $pointsToDeduct;
        if ($point->remaining_points == 0) {
            $point->type = 'Dùng điểm';
        }
        $point->save();
    }

    // Ghi lịch sử sử dụng điểm
    PointHistory::create([
        'membership_id' => $membershipId,
        'points' => -$pointsToUse, // Lưu giá trị ban đầu
        'remaining_points' => -$pointsToUse,
        'type' => 'Dùng điểm',
        'ticket_id' => $ticketId,
        'created_at' => now(),
    ]);

    // Cập nhật số dư điểm trong membership
    $membership->points = max(0, $totalAvailablePoints - $pointsToUse);
    $membership->save();

    return [
        'message' => 'Điểm đã được sử dụng thành công',
        'remaining_points' => $membership->points,
    ];
}

    /**
     * Cập nhật số dư điểm bằng cách xử lý các điểm đã hết hạn.
     *
     * @param int $membershipId
     * @return void
     */
    public function updateExpiredPoints($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
    
        // Tìm các điểm đã hết hạn
        $expiredPoints = PointHistory::where('membership_id', $membershipId)
            ->where('type', 'Nhận điểm')
            ->where('expired_at', '<=', Carbon::now())
            ->get();
    
        $totalExpired = 0;
        foreach ($expiredPoints as $point) {
            $totalExpired += $point->remaining_points;
    
            // Ghi lịch sử hết hạn
            PointHistory::create([
                'membership_id' => $point->membership_id,
                'points' => -$point->remaining_points, // Lưu giá trị ban đầu
                'remaining_points' => -$point->remaining_points,
                'type' => 'Hết hạn',
                'created_at' => now(),
            ]);
    
            // Đánh dấu bản ghi cũ
            $point->type = 'Hết hạn';
            $point->remaining_points = 0; // Đặt remaining_points về 0
            $point->save();
        }
    
        // Cập nhật số dư điểm
        if ($totalExpired > 0) {
            $membership->points = max(0, $membership->points - $totalExpired);
            $membership->save();
        }
    }
}