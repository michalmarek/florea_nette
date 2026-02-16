<?php declare(strict_types=1);

namespace App\Core\Email;

use App\Core\Email\Provider\MaileonProvider;
use App\Core\Email\Provider\NetteMailProvider;
use Tracy\Debugger;

/**
 * Email Service
 *
 * Routes emails to appropriate provider based on message type:
 * - Maileon: customer-facing transactional (has maileonEventId)
 * - SMTP: internal notifications (has htmlBody)
 */
class EmailService
{
    public function __construct(
        private MaileonProvider $maileonProvider,
        private NetteMailProvider $netteMailProvider,
    ) {}

    public function send(EmailMessage $message): void
    {
        try {
            if ($message->isMaileonEmail()) {
                $this->maileonProvider->send($message);
                Debugger::log("Email sent via Maileon to {$message->to} (event #{$message->maileonEventId})", 'email');
            } else {
                $this->netteMailProvider->send($message);
                Debugger::log("Email sent via SMTP to {$message->to}: {$message->subject}", 'email');
            }

        } catch (\Exception $e) {
            Debugger::log($e, Debugger::ERROR);
            throw $e;
        }
    }
}