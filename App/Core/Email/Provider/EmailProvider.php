<?php declare(strict_types=1);

namespace App\Core\Email\Provider;

use App\Core\Email\EmailMessage;

/**
 * Contract for all email providers (Maileon, SMTP, etc.)
 */
interface EmailProvider
{
    public function send(EmailMessage $message): void;
}