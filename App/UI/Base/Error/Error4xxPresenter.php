<?php

declare(strict_types=1);

namespace App\UI\Base\Error;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;

/**
 * Renders 4xx error pages with layout
 */
class Error4xxPresenter extends Presenter
{
    public function startup(): void
    {
        parent::startup();

        $exception = $this->getRequest()->getParameter('exception');
        if (!$exception instanceof BadRequestException) {
            $this->error();
        }
    }

    public function renderDefault(BadRequestException $exception): void
    {
        $code = $exception->getHttpCode();
        $this->template->code = $code;
        $this->template->title = match ($code) {
            403 => 'Přístup zamítnut',
            404 => 'Stránka nenalezena',
            410 => 'Stránka již neexistuje',
            default => 'Chyba',
        };

        // Try code-specific template (4xx.latte), fallback to default
        $file = __DIR__ . "/$code.latte";
        $this->template->setFile(is_file($file) ? $file : __DIR__ . '/4xx.latte');
    }
}