<?php

namespace App\Mail;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RezervacijaPodsetnik extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $termin;

    /**
     * @param  SpaBooking  $booking  the upcoming reservation being reminded about
     */
    public function __construct(public SpaBooking $booking)
    {
        $this->termin = TerminOpis::za($booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Podsetnik: vaš spa termin je uskoro');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rezervacija-podsetnik');
    }
}
