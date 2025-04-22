<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PointsExpiring extends Mailable
{
    use Queueable, SerializesModels;

    public $points;
    public $expiredAt;

    public function __construct($points, $expiredAt)
    {
        $this->points = $points;
        $this->expiredAt = $expiredAt instanceof \Carbon\Carbon ? $expiredAt : \Carbon\Carbon::parse($expiredAt);
    }

    public function build()
    {
        return $this->subject('Your Points Are About to Expire!')
                    ->view('emails.points_expiring')
                    ->with([
                        'points' => $this->points,
                        'expiredAt' => $this->expiredAt,
                    ]);
    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Points Expiring',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.points_expiring',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
