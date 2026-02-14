<?php

declare(strict_types=1);

namespace App\UI\Base\Error;

use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\CallbackResponse;
use Nette\Application\Responses\ForwardResponse;
use Nette\Http;

/**
 * Handles 4xx HTTP errors (not found, forbidden, etc.)
 */
class ErrorPresenter implements IPresenter
{
    public function __construct(
        private Http\IRequest $httpRequest,
    ) {}

    public function run(Request $request): Response
    {
        $exception = $request->getParameter('exception');

        if ($exception instanceof BadRequestException) {
            [$module, , $sep] = \Nette\Application\Helpers::splitName($request->getPresenterName());
            return new ForwardResponse($request->setPresenterName($module . $sep . 'Error4xx'));
        }

        return new CallbackResponse(function (Http\IRequest $httpRequest, Http\IResponse $httpResponse): void {
            if (preg_match('#^text/html#', (string) $httpResponse->getHeader('Content-Type'))) {
                require __DIR__ . '/500.phtml';
            }
        });
    }
}