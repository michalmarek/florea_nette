<?php declare(strict_types=1);

namespace App\Model\Customer;

use DateTimeImmutable;
use Nette\Database\Explorer;

/**
 * PasswordResetTokenRepository
 *
 * Handles database operations for password reset tokens.
 * Database table: es_uzivatele_resetTokens
 *
 * Security features:
 * - Token hash storage (SHA-256, plain token never stored)
 * - Single-use tokens (used_at flag)
 * - Token expiration
 * - Rate limiting support
 * - Automatic garbage collection of expired tokens
 */
class PasswordResetTokenRepository
{
    /** Max reset attempts per time window */
    private const RATE_LIMIT_MAX_ATTEMPTS = 3;

    /** Rate limit window in minutes */
    private const RATE_LIMIT_WINDOW_MINUTES = 15;

    /** Token expiration in seconds (1 hour) */
    private const TOKEN_EXPIRATION_SECONDS = 3600;

    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Create new password reset token
     *
     * Generates random token (32 bytes = 64 hex chars),
     * stores its SHA-256 hash in database,
     * returns the plain token for email.
     */
    public function createToken(int $customerId): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $expiresAt = new DateTimeImmutable('+' . self::TOKEN_EXPIRATION_SECONDS . ' seconds');

        $this->database->table('es_uzivatele_resetTokens')->insert([
            'customer_id' => $customerId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $plainToken;
    }

    /**
     * Find valid token by plain token string
     *
     * Validates: exists, not expired, not used.
     * Also runs garbage collection on expired tokens.
     */
    public function findValidToken(string $plainToken): ?array
    {
        $this->deleteExpiredTokens();

        $tokenHash = hash('sha256', $plainToken);

        $row = $this->database->table('es_uzivatele_resetTokens')
            ->where('token_hash', $tokenHash)
            ->where('expires_at > NOW()')
            ->where('used_at', null)
            ->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'customer_id' => (int) $row->customer_id,
        ];
    }

    /**
     * Mark token as used (prevents reuse)
     */
    public function markAsUsed(int $tokenId): void
    {
        $this->database->table('es_uzivatele_resetTokens')
            ->where('id', $tokenId)
            ->update(['used_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Count recent reset attempts for rate limiting
     */
    public function countRecentAttempts(int $customerId): int
    {
        return $this->database->table('es_uzivatele_resetTokens')
            ->where('customer_id', $customerId)
            ->where('created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)', self::RATE_LIMIT_WINDOW_MINUTES)
            ->count();
    }

    /**
     * Check if rate limit is exceeded
     */
    public function isRateLimited(int $customerId): bool
    {
        return $this->countRecentAttempts($customerId) >= self::RATE_LIMIT_MAX_ATTEMPTS;
    }

    /**
     * Delete expired tokens (garbage collection)
     */
    private function deleteExpiredTokens(): void
    {
        $this->database->table('es_uzivatele_resetTokens')
            ->where('expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')
            ->delete();
    }
}