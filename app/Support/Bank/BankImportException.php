<?php

namespace App\Support\Bank;

use RuntimeException;

/** Fehler beim Lesen eines Kontoauszugs. Die Meldung ist für den Benutzer gedacht. */
class BankImportException extends RuntimeException
{
}
