<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A guest action that cannot be carried out, with a message meant for the
 * guest. Only this exception's message is shown in the guest app; any other
 * error is logged and answered generically, so internal details (ledger
 * checks, SQL errors) never reach the guest.
 */
class GuestActionException extends RuntimeException {}
