<?php declare(strict_types=1);

namespace App\UI\Base\Auth;

use App\Model\Customer\CustomerRepository;
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
    private CustomerRepository $customerRepository;

    public function injectCustomerRepository(CustomerRepository $customerRepository): void
    {
        $this->customerRepository = $customerRepository;
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
                $this->getUser()->setExpiration('30 days', false);
            } else {
                $this->getUser()->setExpiration('30 minutes', true);
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

            $this->flashMessage('Registrace byla úspěšná.', 'success');
            $this->redirect('Home:default');

        } catch (\Exception $e) {
            $form->addError('Při registraci došlo k chybě. Zkuste to prosím znovu.');
        }
    }
}