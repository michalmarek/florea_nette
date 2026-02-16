<?php declare(strict_types=1);

namespace App\Core\Email\Provider;

use App\Core\Email\EmailMessage;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ImportReference;
use de\xqueue\maileon\api\client\transactions\ImportContactReference;
use de\xqueue\maileon\api\client\contacts\Permission;
use InvalidArgumentException;

/**
 * Sends transactional emails via Maileon API.
 * Used for customer-facing emails (password reset, order confirmation).
 */
class MaileonProvider implements EmailProvider
{
    private TransactionsService $transactionsService;

    public function __construct(
        string $apiKey,
        string $baseUrl,
    ) {
        $this->transactionsService = new TransactionsService([
            'BASE_URI' => $baseUrl,
            'API_KEY' => $apiKey,
            'DEBUG' => false,
        ]);
    }

    public function send(EmailMessage $message): void
    {
        if (!$message->isMaileonEmail()) {
            throw new InvalidArgumentException(
                'MaileonProvider requires maileonEventId.'
            );
        }

        try {
            $transaction = new Transaction();
            $transaction->import = new ImportReference();
            $transaction->import->contact = new ImportContactReference();
            $transaction->import->contact->email = $message->to;
            $transaction->import->contact->permission = Permission::$OTHER->getCode();
            $transaction->type = $message->maileonEventId;
            $transaction->content = $message->personalizedData;

            $response = $this->transactionsService->createTransactions(
                [$transaction],
                true,
                false,
            );

            if (!$response->isSuccess()) {
                throw new \Exception(
                    'Maileon API error (Status: ' . $response->getStatusCode() . '): '
                    . ($response->getBodyData() ?: 'Unknown error')
                );
            }

        } catch (\Exception $e) {
            throw new \Exception(
                sprintf(
                    'Failed to send Maileon email to %s (event #%d): %s',
                    $message->to,
                    $message->maileonEventId,
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }
}