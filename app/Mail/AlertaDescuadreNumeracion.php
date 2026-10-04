<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso técnico diario: participantes con número/chip en delivery que no
 * llegaron a ApiRestEvent (ver retiro:alertar-descuadre-numeracion).
 */
class AlertaDescuadreNumeracion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $eventoId,
        public string $resumen,
        public int $casos,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Delivery] Descuadre de numeración en evento {$this->eventoId}: {$this->casos} caso(s)",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.descuadre-numeracion');
    }
}
