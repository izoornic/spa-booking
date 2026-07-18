<?php

namespace App\Mail;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RezervacijaPotvrda extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $termin;

    public function __construct(public SpaBooking $booking)
    {
        $this->termin = TerminOpis::za($booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Potvrda rezervacije spa termina');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rezervacija-potvrda');
    }
}
