<?php

namespace App\Jobs;

use App\Mail\TicketBookedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ticket;

    public function __construct($ticket)
    {
        $this->ticket = $ticket;
    }
    public function handle()
    {
        try {
            Log::info('Dữ liệu vé gửi vào email:', ['ticket' => $this->ticket]);
            Log::info("Đang gửi email tới: " . $this->ticket->user->email);
            Mail::to($this->ticket->user->email)->send(new TicketBookedMail($this->ticket));
            Log::info("Gửi email thành công: " . $this->ticket->user->email);
        } catch (\Exception $e) {
            Log::error('Gửi email thất bại: ' . $e->getMessage());
        }
    }
}
