<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    // Lấy danh sách tất cả tickets
    public function index()
    {
        return response()->json(Ticket::all(), 200);
    }

    // Lấy thông tin 1 ticket theo ID
    public function show($id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }
        return response()->json($ticket, 200);
    }

    // Tạo mới ticket
    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(),[
            'user_id' => 'required|exists:users,id',
            'cinema_id' => 'required|exists:cinemas,id',
            'room_id' => 'required|exists:rooms,id',
            'movie_id' => 'required|exists:movies,id',
            'showtime_id' => 'required|exists:showtimes,id',
            'voucher_code' => 'nullable|string',
            'voucher_discount' => 'nullable|integer',
            'payment_name' => 'required|string',
            'code' => 'required|string|unique:tickets,code',
            'total_price' => 'required|integer',
            'status' => 'nullable|string|in:chưa xuất vé,đã xuất vé',
            'staff' => 'nullable|string',
            'expiry' => 'required|date_format:Y-m-d H:i:s'
        ]);
        if ($validatedData->fails()) {
            return response()->json(['errors' => $validatedData->errors()], 422);
        }
        try {
            $ticket = Ticket::create($request->all());
            return response()->json(['message' => 'Thêm mới thành công!', 'ticket' => $ticket], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Thêm mới thất bại!', 'error' => $th->getMessage()], 500);
        }
       
    }

    // Cập nhật thông tin ticket
    public function update(Request $request, $id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        $validatedData =Validator::make($request->all(),[
            'user_id' => 'sometimes|exists:users,id',
            'cinema_id' => 'sometimes|exists:cinemas,id',
            'room_id' => 'sometimes|exists:rooms,id',
            'movie_id' => 'sometimes|exists:movies,id',
            'showtime_id' => 'sometimes|exists:showtimes,id',
            'voucher_code' => 'nullable|string',
            'voucher_discount' => 'nullable|integer',
            'payment_name' => 'sometimes|string',
            'code' => 'sometimes|string|unique:tickets,code,' . $id,
            'total_price' => 'sometimes|integer',
            'status' => 'sometimes|string|in:chưa xuất vé,đã xuất vé',
            'staff' => 'nullable|string',
            'expiry' => 'sometimes|date'
        ]);
        if ($validatedData->fails()) {
            return response()->json(['errors' => $validatedData->errors()], 422);
        }
       
        try {
            $ticket->update($request->all());
            return response()->json(['message' => 'Sửa mới thành công!', 'ticket' => $ticket], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Sửa mới thất bại!', 'error' => $th->getMessage()], 500);
        }
    }

    // Xóa ticket
    public function destroy($id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        $ticket->delete();
        return response()->json(['message' => 'Ticket deleted'], 200);
    }
}
