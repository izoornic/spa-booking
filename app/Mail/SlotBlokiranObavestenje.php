<?php

namespace App\Mail;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlotBlokiranObavestenje extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $termin;

    /**
     * @param  SpaBooking  $booking  the reservation that was cancelled by the blockade
     * @param  string|null  $razlog  optional reason shown to the owner
     */
    public function __construct(public SpaBooking $booking, public ?string $razlog = null)
    {
        $this->termin = TerminOpis::za($booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vaš spa termin je otkazan (zatvaranje termina)');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.slot-blokiran');
    }
}
