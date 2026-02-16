<?php declare(strict_types=1);

namespace App\Core\Email;

use InvalidArgumentException;

/**
 * Email Message DTO
 *
 * Routing determined by presence of maileonEventId:
 * - maileonEventId set → Maileon provider (customer-facing transactional)
 * - htmlBody set → SMTP provider (internal notifications)
 */
class EmailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,

        // Maileon (transactional events)
        public readonly ?int $maileonEventId = null,
        public readonly array $personalizedData = [],

        // SMTP (direct send)
        public readonly ?string $htmlBody = null,
        public readonly ?string $textBody = null,

        // Optional sender override
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
    ) {
        if ($this->maileonEventId === null && $this->htmlBody === null) {
            throw new InvalidArgumentException(
                'EmailMessage must have either maileonEventId (for Maileon) or htmlBody (for SMTP).'
            );
        }

        if ($this->maileonEventId !== null && $this->htmlBody !== null) {
            throw new InvalidArgumentException(
                'EmailMessage cannot have both maileonEventId and htmlBody. Choose one provider.'
            );
        }

        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$this->to}");
        }
    }

    public function isMaileonEmail(): bool
    {
        return $this->maileonEventId !== null;
    }

    public function isSmtpEmail(): bool
    {
        return $this->htmlBody !== null;
    }
}