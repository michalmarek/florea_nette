<?php declare(strict_types=1);

namespace App\Model\Customer;

use App\Core\Email\EmailMessage;
use App\Core\Email\EmailService;
use App\Shop\ShopContext;

/**
 * PasswordResetService
 *
 * Handles password reset flow:
 * - requestReset: validate email, rate limit, create token, send email
 * - validateToken: check token validity
 * - resetPassword: validate token, update password, mark token used
 */
class PasswordResetService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private EmailService $emailService,
        private ShopContext $shopContext,
        private int $passwordResetEventId,
    ) {}

    /**
     * Request password reset for email
     *
     * @throws \Exception If email not found or rate limited
     */
    public function requestReset(string $email): void
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer) {
            throw new \Exception('Email neexistuje v systému.');
        }

        if ($this->tokenRepository->isRateLimited($customer->id)) {
            throw new \Exception('Příliš mnoho pokusů. Zkuste to znovu za 15 minut.');
        }

        $plainToken = $this->tokenRepository->createToken($customer->id);

        $this->sendResetEmail($customer, $plainToken);
    }

    /**
     * Validate reset token
     *
     * @return array|null Token data [id, customer_id] or null if invalid
     */
    public function validateToken(string $token): ?array
    {
        return $this->tokenRepository->findValidToken($token);
    }

    /**
     * Reset password using token
     *
     * @throws \Exception If token is invalid
     * @return int Customer ID (for redirect or auto-login)
     */
    public function resetPassword(string $token, string $newPassword): int
    {
        $tokenData = $this->validateToken($token);

        if (!$tokenData) {
            throw new \Exception('Neplatný nebo expirovaný odkaz pro reset hesla.');
        }

        $customerId = $tokenData['customer_id'];

        $this->customerRepository->updatePassword($customerId, $newPassword);
        $this->tokenRepository->markAsUsed($tokenData['id']);

        return $customerId;
    }

    /**
     * Send password reset email via Maileon
     */
    private function sendResetEmail(Customer $customer, string $plainToken): void
    {
        $shopUrl = $this->shopContext->getUrl();
        $resetLink = "{$shopUrl}reset-hesla?token={$plainToken}";

        $message = new EmailMessage(
            to: $customer->email,
            subject: 'Reset hesla',
            maileonEventId: $this->passwordResetEventId,
            personalizedData: [
                'email' => $customer->email,
                'reset_url' => $resetLink,
                'shopID' => $customer->shopId,
                'lang' => 'cs',
            ],
        );

        $this->emailService->send($message);
    }
}