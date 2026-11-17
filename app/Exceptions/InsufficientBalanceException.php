<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the create-generation transaction when the user's balance
 * can't cover the estimate, so the transaction rolls back the Generation
 * row along with the declined hold - never leaves an orphaned row behind.
 */
class InsufficientBalanceException extends RuntimeException
{
    //
}
