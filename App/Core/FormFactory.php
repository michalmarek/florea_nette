<?php declare(strict_types=1);

namespace App\Core;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;

/**
 * FormFactory
 *
 * Creates forms with Bootstrap 5 styling applied.
 * Usage: $form = $this->formFactory->create();
 */
class FormFactory
{
    public function create(): Form
    {
        $form = new Form;
        $this->applyBootstrapRenderer($form);
        return $form;
    }

    private function applyBootstrapRenderer(Form $form): void
    {
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'div class="mb-3"';
        $renderer->wrappers['pair']['.error'] = 'has-danger';
        $renderer->wrappers['control']['container'] = 'div';
        $renderer->wrappers['label']['container'] = 'div';
        $renderer->wrappers['control']['description'] = 'span class="form-text"';
        $renderer->wrappers['control']['errorcontainer'] = 'span class="invalid-feedback d-block"';
        $renderer->wrappers['error']['container'] = 'div class="alert alert-danger"';
        $renderer->wrappers['error']['item'] = 'p class="m-0"';

        $form->onRender[] = function (Form $form): void {
            foreach ($form->getControls() as $control) {
                $type = $control->getOption('type');

                if ($control instanceof SubmitButton) {
                    $control->getControlPrototype()->addClass('btn btn-primary');
                } elseif (in_array($type, ['text', 'email', 'password', 'tel', 'textarea'], true)) {
                    $control->getControlPrototype()->addClass('form-control');
                } elseif ($type === 'select') {
                    $control->getControlPrototype()->addClass('form-select');
                } elseif ($type === 'checkbox') {
                    $control->getControlPrototype()->addClass('form-check-input');
                    $control->getLabelPrototype()->addClass('form-check-label');
                } elseif ($type === 'radio') {
                    $control->getControlPrototype()->addClass('form-check-input');
                    $control->getLabelPrototype()->addClass('form-check-label');
                }

                if ($control->hasErrors()) {
                    $control->getControlPrototype()->addClass('is-invalid');
                }
            }
        };
    }
}