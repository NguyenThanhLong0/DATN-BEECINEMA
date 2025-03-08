<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Ticket;

class TicketBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;

    public function __construct(Ticket $ticket)
    {
        // Load tất cả các mối quan hệ cần thiết để đảm bảo dữ liệu đầy đủ
        $this->ticket = Ticket::with([
            'ticketSeats.seat',
            'room',
            'showtime',
            'cinema',
            'movie',
            'combos.combo'
        ])->find($ticket->id);

        // Đảm bảo giá trị không bị null
        $this->ticket->concession_amount = $this->ticket->combos->sum(function ($combo) {
            return $combo->quantity * ($combo->combo->price ?? 0);
        });

        $this->ticket->discount_amount = ($this->ticket->voucher_discount ?? 0) + ($this->ticket->point_discount ?? 0);

        $this->ticket->total_amount = $this->ticket->total_price + $this->ticket->discount_amount;
    }

    public function build()
    {
        $this->ticket->load('ticketSeats.seat'); // Load quan hệ trước khi truyền vào view

        return $this->subject('Vé đã được đặt thành công')
            ->view('emails.ticket_booked')
            ->with([
                'ticket' => $this->ticket
            ]);
    }
}
