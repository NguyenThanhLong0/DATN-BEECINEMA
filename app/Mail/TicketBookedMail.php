<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class TicketBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $paymentData;

    public function __construct(Ticket $ticket, $paymentData)
    {
        //Load tất cả các mối quan hệ cần thiết để đảm bảo dữ liệu đầy đủ
        $this->ticket = Ticket::with([
            'ticketSeats.seat',
            'room',
            'showtime',
            'cinema',
            'movie',
            'combos.combo'
        ])->find($ticket->id);

        $this->paymentData = $paymentData; // Lưu paymentData để dùng trong email

        // Log các giá trị để kiểm tra
        Log::info('Seat Amount:', ['seat_amount' => $this->paymentData['seat_amount'] ?? 'NULL']);
        Log::info('Combo Amount:', ['combo_amount' => $this->paymentData['combo_amount'] ?? 'NULL']);
        Log::info('Total Price Before Discount:', ['total_price_before_discount' => $this->paymentData['total_price_before_discount'] ?? 'NULL']);
        Log::info('Total Discount:', ['total_discount' => $this->paymentData['total_discount'] ?? 'NULL']);

        // Đảm bảo giá trị không bị null
        // $this->ticket->concession_amount = $this->ticket->combos->sum(function ($combo) {
        //     return $combo->quantity * ($combo->combo->price ?? 0);
        // });
        // // thêm tổng tiền ghế
        // $this->ticket->seat_amount = $this->ticket->ticketSeats->sum(function ($ticketSeat) {
        //     return $ticketSeat->price ?? 0;
        // });


        // // $this->ticket->discount_amount = ($this->ticket->voucher_discount ?? 0) + ($this->ticket->point_discount ?? 0);

        // // $this->ticket->total_amount = $this->ticket->total_price + $this->ticket->discount_amount;

        // // TODO: Thêm giảm giá vé và combo từ controller vào đây
        // $this->ticket->ticket_discount = $this->ticket->ticket_discount ?? 0;
        // $this->ticket->combo_discount = $this->ticket->combo_discount ?? 0;

        // $rank = $this->ticket->user->membership->rank;
        // if ($rank) {
        //     $this->ticket->ticket_discount = $this->ticket->seat_amount * ($rank->ticket_percentage / 100); // Giảm giá vé
        //     $this->ticket->combo_discount = $this->ticket->concession_amount * ($rank->combo_percentage / 100); // Giảm giá combo
        // }

        // // Cộng tất cả các khoản giảm giá
        // $this->ticket->total_discount = $this->ticket->voucher_discount + $this->ticket->point_discount + $this->ticket->ticket_discount + $this->ticket->combo_discount;

        // //tổng tiền chưa giảm giá
        // $this->ticket->total_price_beforeDiscount = $this->ticket->seat_amount + $this->ticket->concession_amount;

        // // Tính tổng tiền thanh toán sau giảm giá
        // $this->ticket->total_price = $this->ticket->total_price_beforeDiscount - $this->ticket->total_discount;




        // // Log tổng tiền combo
        // Log::info('Concession Amount: ', ['concession_amount' => $this->ticket->concession_amount]);
        // // Log tổng tiền ghế
        // Log::info('Seat Amount: ', ['seat_amount' => $this->ticket->seat_amount]);
        // // Log các giảm giá
        // Log::info('Ticket Discount: ', ['ticket_discount' => $this->ticket->ticket_discount]);
        // Log::info('Combo Discount: ', ['combo_discount' => $this->ticket->combo_discount]);
        // // Log tổng giảm giá
        // Log::info('Total Discount: ', ['total_discount' => $this->ticket->total_discount]);
        // // Log tổng tiền chưa giảm giá
        // Log::info('Total Price Before Discount: ', ['total_price_beforeDiscount' => $this->ticket->total_price_beforeDiscount]);
        // // Log tổng tiền thanh toán
        // Log::info('Total Price: ', ['total_price' => $this->ticket->total_price]);

        // Log tổng tiền combo
        Log::info('Concession Amount: ', ['concession_amount' => $this->ticket->combo_amount]);
    }

    public function build()
    {
        $this->ticket->load('ticketSeats.seat'); // Load quan hệ trước khi truyền vào view

        return $this->subject('Vé đã được đặt thành công')
            ->view('emails.ticket_booked')
            ->with([
                'ticket' => $this->ticket,
                'paymentData' => $this->paymentData, // Truyền paymentData vào Blade
            ]);
    }
}
