<?php

namespace App\Jobs;

use App\Mail\TicketBookedMail;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticket;
    public $paymentData;

    public function __construct($ticket, $paymentData)
    {
        $this->ticket = $ticket;
        $this->paymentData = $paymentData;
    }
    public function handle()
    {
        try {
            // Lấy ticket từ DB
            $ticket = Ticket::where('id', $this->ticket->id)->first();

            if (!$ticket) {
                Log::error('Không tìm thấy ticket với ID: ' . $this->ticket->id);
                return;
            }

            // Lấy dữ liệu payment từ cache
            $paymentData = Cache::get("payment_{$ticket->code}");


            // Xác nhận đúng mã code của ticket
            $cacheKey = "payment_{$ticket->code}";
            Log::info("Đang kiểm tra cache với key: " . $cacheKey);

            $paymentData = Cache::get($cacheKey);

            if (!$paymentData) {
                Log::error("Không tìm thấy paymentData từ cache cho ticket: {$ticket->code}");
                return;
            }

            Log::info("Tìm thấy paymentData từ cache:", ['paymentData' => $paymentData]);

            Log::info('Dữ liệu vé gửi vào email:', ['ticket' => $ticket, 'paymentData' => $paymentData]);

            // Gửi cả ticket và paymentData
            Mail::to($ticket->user->email)->send(new TicketBookedMail($ticket, $paymentData));

            // Xóa cache sau khi email đã được gửi
            Cache::forget($cacheKey);
            Log::info("Xóa cache sau khi gửi email thành công: " . $cacheKey);

            Log::info('Dữ liệu vé gửi vào email:', ['ticket' => $this->ticket]);
            Log::info("Đang gửi email tới: " . $this->ticket->user->email);
            Log::info("Gửi email thành công: " . $this->ticket->user->email);
        } catch (\Exception $e) {
            Log::error('Gửi email thất bại: ' . $e->getMessage());
        }
    }
}
