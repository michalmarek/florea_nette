<?php declare(strict_types=1);

namespace App\Model\Customer;

use Nette\Security\Authenticator;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;

/**
 * CustomerAuthenticator
 *
 * Implements Nette\Security\Authenticator for customer login.
 * Used automatically by Nette\Security\User::login($login, $password).
 *
 * Returns SimpleIdentity with:
 * - id: Customer ID
 * - roles: ['customer']
 * - data: firstName, lastName, email (available via $user->identity->firstName)
 *
 * Handles:
 * - Login not found → AuthenticationException
 * - Inactive account → AuthenticationException
 * - Wrong password → AuthenticationException
 * - Success → updates lastLogin timestamp, returns identity
 */
class CustomerAuthenticator implements Authenticator
{
    public function __construct(
        private CustomerRepository $customerRepository,
    ) {}

    /**
     * Authenticate customer by login and password
     *
     * Called by Nette\Security\User::login($login, $password).
     * On success, Nette automatically stores identity in session.
     *
     * @throws AuthenticationException If login fails
     */
    public function authenticate(string $username, string $password): IIdentity
    {
        $customer = $this->customerRepository->findByLogin($username);

        if (!$customer) {
            throw new AuthenticationException(
                'Nesprávné přihlašovací údaje.',
                self::IdentityNotFound,
            );
        }

        if (!$customer->active) {
            throw new AuthenticationException(
                'Účet není aktivní.',
                self::NotApproved,
            );
        }

        if (!$customer->verifyPassword($password)) {
            throw new AuthenticationException(
                'Nesprávné přihlašovací údaje.',
                self::InvalidCredential,
            );
        }

        // Update last login timestamp
        $this->customerRepository->updateLastLogin($customer->id);

        return new SimpleIdentity(
            $customer->id,
            ['customer'],
            [
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'email' => $customer->email,
                'login' => $customer->login,
            ],
        );
    }
}