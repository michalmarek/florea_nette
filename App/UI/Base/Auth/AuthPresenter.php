<?php declare(strict_types=1);

namespace App\UI\Base\Auth;

use App\Model\Customer\CustomerRepository;
use App\Model\Customer\PasswordResetService;
use App\UI\Base\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

/**
 * AuthPresenter
 *
 * Handles customer authentication: login, logout, registration.
 * Uses Nette\Security\User with CustomerAuthenticator.
 *
 * Routes (configured in shop neon):
 * - /prihlaseni → Auth:login
 * - /odhlaseni → Auth:logout
 * - /registrace → Auth:register
 */
class AuthPresenter extends BasePresenter
{
    private const EXPIRATION_REMEMBER = '30 days';
    private const EXPIRATION_SESSION = '30 minutes';
    private CustomerRepository $customerRepository;
    private PasswordResetService $passwordResetService;

    public function injectCustomerRepository(CustomerRepository $customerRepository): void
    {
        $this->customerRepository = $customerRepository;
    }

    public function injectPasswordResetService(\App\Model\Customer\PasswordResetService $passwordResetService): void
    {
        $this->passwordResetService = $passwordResetService;
    }

    // =====================================================================
    // Login
    // =====================================================================

    /**
     * Login page — redirect if already logged in
     */
    public function actionLogin(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('Home:default');
        }
    }

    public function renderLogin(): void
    {
        $this->template->pageTitle = 'Přihlášení';
    }

    /**
     * Login form component
     */
    protected function createComponentLoginForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addText('login', 'Přihlašovací jméno:')
            ->setRequired('Vyplňte přihlašovací jméno');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Vyplňte heslo');

        $form->addCheckbox('rememberMe', 'Zapamatovat si mě');

        $form->addSubmit('submit', 'Přihlásit se');

        $form->onSuccess[] = $this->loginFormSucceeded(...);

        return $form;
    }

    /**
     * Process login form
     */
    private function loginFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            if ($values->rememberMe) {
                $this->getUser()->setExpiration(self::EXPIRATION_REMEMBER, false);
            } else {
                $this->getUser()->setExpiration(self::EXPIRATION_SESSION, true);
            }

            $this->getUser()->login($values->login, $values->password);

            $this->flashMessage('Byli jste úspěšně přihlášeni.', 'success');

            $backUrl = $this->getParameter('backUrl');
            if ($backUrl) {
                $this->redirectUrl($backUrl);
            } else {
                $this->redirect('Home:default');
            }

        } catch (AuthenticationException $e) {
            $form->addError('Nesprávné přihlašovací jméno nebo heslo.');
        }
    }

    // =====================================================================
    // Logout
    // =====================================================================

    /**
     * Logout and redirect to homepage
     */
    public function actionLogout(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->getUser()->logout(true);
            $this->flashMessage('Byli jste odhlášeni.', 'info');
        }

        $this->redirect('Home:default');
    }

    // =====================================================================
    // Registration
    // =====================================================================

    /**
     * Registration page — redirect if already logged in
     */
    public function actionRegister(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('Home:default');
        }
    }

    public function renderRegister(): void
    {
        $this->template->pageTitle = 'Registrace';
    }

    /**
     * Registration form component
     */
    protected function createComponentRegisterForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addText('login', 'Přihlašovací jméno:')
            ->setRequired('Vyplňte přihlašovací jméno')
            ->addRule(Form::MinLength, 'Login musí mít alespoň %d znaky', 3);

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail');

        $form->addText('firstName', 'Jméno:')
            ->setRequired('Vyplňte jméno');

        $form->addText('lastName', 'Příjmení:')
            ->setRequired('Vyplňte příjmení');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Vyplňte heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('passwordVerify', 'Heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['password']);

        $form->addSubmit('submit', 'Registrovat se');

        $form->onSuccess[] = $this->registerFormSucceeded(...);

        return $form;
    }

    /**
     * Process registration form
     */
    private function registerFormSucceeded(Form $form, \stdClass $values): void
    {
        if ($this->customerRepository->loginExists($values->login)) {
            $form->addError('Toto přihlašovací jméno je již obsazené.');
            return;
        }

        if ($this->customerRepository->findByEmail($values->email)) {
            $form->addError('Tento e-mail je již registrován.');
            return;
        }

        try {
            $this->customerRepository->create([
                'login' => $values->login,
                'email' => $values->email,
                'firstName' => $values->firstName,
                'lastName' => $values->lastName,
                'password' => $values->password,
                'shopId' => $this->shopContext->getId(),
            ]);

            // Auto-login after registration
            $this->getUser()->login($values->login, $values->password);

        } catch (\Exception $e) {
            $form->addError('Při registraci došlo k chybě. Zkuste to prosím znovu.');
            return;
        }

        $this->flashMessage('Registrace byla úspěšná.', 'success');
        $this->redirect('Home:default');
    }


    // =====================================================================
    // Forgot Password
    // =====================================================================

    public function actionForgotPassword(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('Home:default');
        }
    }

    public function renderForgotPassword(): void
    {
        $this->template->pageTitle = 'Zapomenuté heslo';
    }

    protected function createComponentForgotPasswordForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail')
            ->setHtmlAttribute('placeholder', 'vas@email.cz');

        $form->addSubmit('submit', 'Odeslat odkaz pro reset hesla');

        $form->onSuccess[] = $this->forgotPasswordFormSucceeded(...);

        return $form;
    }

    private function forgotPasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->passwordResetService->requestReset($values->email);
        } catch (\Exception $e) {
            $form->addError($e->getMessage());
            return;
        }

        $this->flashMessage('Odkaz pro reset hesla byl odeslán na váš e-mail.', 'success');
        $this->redirect('Auth:login');
    }

    // =====================================================================
    // Reset Password (from email link)
    // =====================================================================

    public function actionResetPassword(string $token = null): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('Home:default');
        }

        if (!$token) {
            $this->flashMessage('Neplatný odkaz pro reset hesla.', 'danger');
            $this->redirect('Auth:login');
        }

        // Validate token only on GET (initial page load), not on form POST
        if (!$this->isAjax() && !$this->getHttpRequest()->isMethod('POST')) {
            $tokenData = $this->passwordResetService->validateToken($token);

            if (!$tokenData) {
                $this->flashMessage('Odkaz pro reset hesla je neplatný nebo vypršel.', 'danger');
                $this->redirect('Auth:forgotPassword');
            }
        }
    }

    public function renderResetPassword(): void
    {
        $this->template->pageTitle = 'Nastavení nového hesla';
    }

    protected function createComponentResetPasswordForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addHidden('token')
            ->setDefaultValue($this->getParameter('token'));

        $form->addPassword('password', 'Nové heslo:')
            ->setRequired('Vyplňte nové heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('passwordVerify', 'Nové heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['password']);

        $form->addSubmit('submit', 'Změnit heslo');

        $form->onSuccess[] = $this->resetPasswordFormSucceeded(...);

        return $form;
    }

    private function resetPasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->passwordResetService->resetPassword(
                $values->token,
                $values->password,
            );
        } catch (\Exception $e) {
            $form->addError($e->getMessage());
            return;
        }

        $this->flashMessage('Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.', 'success');
        $this->redirect('Auth:login');
    }
}