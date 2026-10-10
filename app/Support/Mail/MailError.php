<?php

namespace App\Support\Mail;

use RuntimeException;

/** Verständlicher Hinweis, warum etwas nicht versendet werden kann. */
class MailError extends RuntimeException
{
}
