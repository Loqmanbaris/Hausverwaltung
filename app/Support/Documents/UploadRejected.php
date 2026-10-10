<?php

namespace App\Support\Documents;

use RuntimeException;

/** Eine hochgeladene Datei wurde abgelehnt. Die Meldung ist für Anwender gedacht. */
class UploadRejected extends RuntimeException
{
}
