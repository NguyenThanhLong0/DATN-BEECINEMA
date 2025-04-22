<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PointService;
use App\Models\Membership;

class PointController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function getAvailablePoints()
    {
        $userId = auth()->id();
        $membership = Membership::where('user_id', $userId)->first();
        if (!$membership) {
            return response()->json([
                'message' => 'Membership not found',
                'available_points' => 0,
            ], 404);
        }
    
        $availablePoints = $this->pointService->getAvailablePoints($membership->id);
        return response()->json([
            'message' => 'Available points retrieved successfully',
            'available_points' => $availablePoints,
        ]);
    }
}