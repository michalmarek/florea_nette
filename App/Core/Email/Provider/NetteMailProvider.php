<?php declare(strict_types=1);

namespace App\Core\Email\Provider;

use App\Core\Email\EmailMessage;
use Nette\Mail\Message;
use Nette\Mail\Mailer;
use InvalidArgumentException;

/**
 * Sends emails via SMTP using Nette Mail.
 * Used for internal notifications and admin emails.
 */
class NetteMailProvider implements EmailProvider
{
    public function __construct(
        private Mailer $mailer,
        private string $defaultFromEmail,
        private string $defaultFromName,
    ) {}

    public function send(EmailMessage $message): void
    {
        if (!$message->isSmtpEmail()) {
            throw new InvalidArgumentException(
                'NetteMailProvider requires htmlBody.'
            );
        }

        $mail = new Message();
        $mail->setFrom(
            $message->fromEmail ?? $this->defaultFromEmail,
            $message->fromName ?? $this->defaultFromName,
        );
        $mail->addTo($message->to);
        $mail->setSubject($message->subject);

        if ($message->htmlBody !== null) {
            $mail->setHtmlBody($message->htmlBody, null);
        }

        if ($message->textBody !== null) {
            $mail->setBody($message->textBody);
        }

        try {
            $this->mailer->send($mail);
        } catch (\Exception $e) {
            throw new \Exception(
                "Failed to send SMTP email to {$message->to}: " . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}