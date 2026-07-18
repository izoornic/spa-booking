<?php

namespace App\Mail;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RezervacijaObavestenjeUpravniku extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $termin;

    /**
     * @param  SpaBooking  $booking  the newly created reservation
     */
    public function __construct(public SpaBooking $booking)
    {
        $this->termin = TerminOpis::za($booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nova spa rezervacija');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rezervacija-upravnik');
    }
}
