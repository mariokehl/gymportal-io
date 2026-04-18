<?php

namespace App\Mail\Contracts;

use Illuminate\Contracts\Mail\Mailable;

/**
 * Marker-Interface für Mailables, die an ein {@see \App\Models\Member}
 * adressiert sind und über den {@see \App\Mail\Dispatching\MemberMailDispatcher}
 * versendet werden dürfen.
 *
 * Ein Mailable, das dieses Interface implementiert, garantiert:
 *   - Ziel ist ein Member (nicht User, nicht Gym, nicht externer Empfänger)
 *   - Versand läuft über den zentralen Dispatcher und wird damit gegen
 *     alle registrierten {@see \App\Mail\Policies\MemberMailPolicy} geprüft.
 */
interface MemberMail extends Mailable
{
}
