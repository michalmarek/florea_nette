<?php declare(strict_types=1);

namespace App\UI\Base\Account;

use App\Model\Customer\Customer;
use App\Model\Customer\CustomerRepository;
use App\Model\Customer\DeliveryAddressRepository;
use App\Model\Customer\DeliveryAddress;
use App\UI\Base\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Security\SimpleIdentity;

/**
 * AccountPresenter
 *
 * Customer account management: profile, password change, delivery addresses.
 * All actions require login (enforced in startup).
 *
 * Signals (handle*) for stateless operations:
 * - handleDeleteAddress(int $id)
 * - handleSetDefaultAddress(int $id)
 */
class AccountPresenter extends BasePresenter
{
    private CustomerRepository $customerRepository;
    private DeliveryAddressRepository $deliveryAddressRepository;

    public function injectCustomerRepository(CustomerRepository $customerRepository): void
    {
        $this->customerRepository = $customerRepository;
    }

    public function injectDeliveryAddressRepository(DeliveryAddressRepository $deliveryAddressRepository): void
    {
        $this->deliveryAddressRepository = $deliveryAddressRepository;
    }

    /**
     * Require login for all actions in this presenter
     */
    protected function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Auth:login', ['backUrl' => $this->storeRequest()]);
        }
    }

    /**
     * Load full Customer entity from database
     */
    private function getCustomer(): Customer
    {
        $customer = $this->customerRepository->findById($this->getUser()->getId());

        if (!$customer) {
            $this->getUser()->logout(true);
            $this->redirect('Auth:login');
        }

        return $customer;
    }

    /**
     * Load delivery address and verify it belongs to current customer.
     * Redirects to addresses list if not found or not owned.
     */
    private function getOwnedAddress(int $id): DeliveryAddress
    {
        $address = $this->deliveryAddressRepository->findById($id);

        if (!$address || $address->customerId !== $this->getUser()->getId()) {
            $this->flashMessage('Adresa nebyla nalezena.', 'danger');
            $this->redirect('Account:addresses');
        }

        return $address;
    }

    // =====================================================================
    // Profile
    // =====================================================================

    public function renderProfile(): void
    {
        $this->template->pageTitle = 'Můj profil';
        $this->template->customer = $this->getCustomer();
    }

    // =====================================================================
    // Edit Profile
    // =====================================================================

    public function actionEdit(): void
    {
        $customer = $this->getCustomer();

        /** @var Form $form */
        $form = $this->getComponent('profileForm');
        if (!$form->isSubmitted()) {
            $form->setDefaults([
                'email' => $customer->email,
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'phone' => $customer->phone ?? '',
                'companyName' => $customer->companyName ?? '',
            ]);
        }
    }

    public function renderEdit(): void
    {
        $this->template->pageTitle = 'Upravit profil';
    }

    protected function createComponentProfileForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail');

        $form->addText('firstName', 'Jméno:')
            ->setRequired('Vyplňte jméno');

        $form->addText('lastName', 'Příjmení:')
            ->setRequired('Vyplňte příjmení');

        $form->addText('phone', 'Telefon:');

        $form->addText('companyName', 'Název firmy:');

        $form->addSubmit('submit', 'Uložit změny');

        $form->onSuccess[] = $this->profileFormSucceeded(...);

        return $form;
    }

    private function profileFormSucceeded(Form $form, \stdClass $values): void
    {
        $customer = $this->getCustomer();

        if ($values->email !== $customer->email) {
            if ($this->customerRepository->emailExistsForAnotherCustomer($values->email, $customer->id)) {
                $form->addError('Tento email již používá jiný zákazník.');
                return;
            }
        }

        try {
            $this->customerRepository->update($customer->id, [
                'email' => $values->email,
                'firstName' => $values->firstName,
                'lastName' => $values->lastName,
                'phone' => $values->phone,
                'companyName' => $values->companyName,
            ]);

            // Update identity in session with new data
            $identity = $this->getUser()->getIdentity();
            $this->getUser()->login(new SimpleIdentity(
                $identity->getId(),
                $identity->getRoles(),
                [
                    'firstName' => $values->firstName,
                    'lastName' => $values->lastName,
                    'email' => $values->email,
                    'login' => $identity->login,
                ],
            ));

            $this->flashMessage('Profil byl úspěšně aktualizován.', 'success');
            $this->redirect('Account:profile');

        } catch (\Exception $e) {
            $form->addError('Při ukládání došlo k chybě. Zkuste to prosím znovu.');
        }
    }

    // =====================================================================
    // Change Password
    // =====================================================================

    public function renderChangePassword(): void
    {
        $this->template->pageTitle = 'Změnit heslo';
    }

    protected function createComponentPasswordForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addPassword('currentPassword', 'Současné heslo:')
            ->setRequired('Vyplňte současné heslo');

        $form->addPassword('newPassword', 'Nové heslo:')
            ->setRequired('Vyplňte nové heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('newPasswordVerify', 'Nové heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['newPassword']);

        $form->addSubmit('submit', 'Změnit heslo');

        $form->onSuccess[] = $this->passwordFormSucceeded(...);

        return $form;
    }

    private function passwordFormSucceeded(Form $form, \stdClass $values): void
    {
        $customer = $this->getCustomer();

        if (!$customer->verifyPassword($values->currentPassword)) {
            $form->addError('Současné heslo není správné.');
            return;
        }

        try {
            $this->customerRepository->updatePassword($customer->id, $values->newPassword);

            $this->flashMessage('Heslo bylo úspěšně změněno.', 'success');
            $this->redirect('Account:profile');

        } catch (\Exception $e) {
            $form->addError('Při změně hesla došlo k chybě.');
        }
    }

    // =====================================================================
    // Delivery Addresses
    // =====================================================================

    public function renderAddresses(): void
    {
        $this->template->pageTitle = 'Doručovací adresy';
        $this->template->addresses = $this->deliveryAddressRepository
            ->findByCustomerId($this->getUser()->getId());
    }

    // --- Edit Address (action — has its own page) ---

    public function actionEditAddress(int $id): void
    {
        $address = $this->getOwnedAddress($id);
        $this->template->editAddress = $address;

        /** @var Form $form */
        $form = $this->getComponent('addressForm');
        if (!$form->isSubmitted()) {
            $form->setDefaults([
                'name' => $address->name,
                'companyName' => $address->companyName ?? '',
                'firstName' => $address->firstName,
                'street' => $address->street,
                'city' => $address->city,
                'postalCode' => $address->postalCode,
                'phone' => $address->phone ?? '',
                'courierNote' => $address->courierNote ?? '',
                'isDefault' => $address->isDefault,
            ]);
        }
    }

    public function renderEditAddress(): void
    {
        $this->template->pageTitle = 'Upravit adresu';
    }

    // --- Signals (stateless operations, no own page) ---

    public function handleSetDefaultAddress(int $id): void
    {
        $this->getOwnedAddress($id);
        $this->deliveryAddressRepository->setAsDefault($id);
        $this->flashMessage('Výchozí adresa byla nastavena.', 'success');
        $this->redirect('this');
    }

    public function handleDeleteAddress(int $id): void
    {
        $this->getOwnedAddress($id);
        $this->deliveryAddressRepository->delete($id);
        $this->flashMessage('Adresa byla smazána.', 'success');
        $this->redirect('this');
    }

    // --- Address Form (shared for add + edit) ---

    protected function createComponentAddressForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addText('name', 'Název adresy:')
            ->setRequired('Vyplňte název (např. Domů, Do práce...)')
            ->setHtmlAttribute('placeholder', 'Domů, Do práce...');

        $form->addText('companyName', 'Název firmy:');

        $form->addText('firstName', 'Jméno a příjmení:')
            ->setRequired('Vyplňte jméno');

        $form->addText('street', 'Ulice a číslo:')
            ->setRequired('Vyplňte ulici');

        $form->addText('city', 'Město:')
            ->setRequired('Vyplňte město');

        $form->addText('postalCode', 'PSČ:')
            ->setRequired('Vyplňte PSČ')
            ->addRule(Form::Pattern, 'PSČ musí být ve formátu XXX XX', '[0-9]{3}\s?[0-9]{2}');

        $form->addText('phone', 'Telefon:')
            ->setHtmlAttribute('placeholder', '123 456 789');

        $form->addTextArea('courierNote', 'Poznámka pro kurýra:')
            ->setHtmlAttribute('rows', 2)
            ->setHtmlAttribute('placeholder', 'Např. zvonek u vrat, 2. patro...');

        $form->addCheckbox('isDefault', 'Nastavit jako výchozí adresu');

        $form->addSubmit('submit', 'Uložit adresu');

        $form->onSuccess[] = $this->addressFormSucceeded(...);

        return $form;
    }

    private function addressFormSucceeded(Form $form, \stdClass $values): void
    {
        $customerId = $this->getUser()->getId();
        $addressId = $this->getParameter('id');

        try {
            if ($addressId) {
                $this->getOwnedAddress((int) $addressId);
                $this->deliveryAddressRepository->update((int) $addressId, (array) $values);
                $this->flashMessage('Adresa byla úspěšně upravena.', 'success');
            } else {
                $data = (array) $values;
                $data['customerId'] = $customerId;
                $this->deliveryAddressRepository->create($data);
                $this->flashMessage('Adresa byla úspěšně přidána.', 'success');
            }

            $this->redirect('Account:addresses');

        } catch (\Exception $e) {
            $form->addError('Při ukládání adresy došlo k chybě.');
        }
    }
}