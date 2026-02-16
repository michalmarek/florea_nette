<?php

declare(strict_types=1);

namespace App\Core\Latte;

use Latte\Extension;

/**
 * IconExtension - Latte extension pro {icon} makro
 *
 * Registruje custom makro {icon} do Latte šablon.
 * Umožňuje vkládat SVG ikony ze sprite souboru s automatickým
 * verzováním přes Nette\Assets a AssetMapper.
 *
 * Metody:
 * - getTags() - Vrací asociativní pole s definicí makra a jeho handlerem
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new IconExtension);
 *
 * Použití v šablonách:
 * {icon 'user'}
 * {icon 'arrow-right'}
 * {icon 'user', class: 'icon--large'}
 * {icon 'user', class: 'icon--large', sprite: 'icons-alt.svg'}
 *
 * Výstup:
 * <span class="icon"><svg><use href="/assets/images/icons-3b5b8812eb.svg#icon-user" /></svg></span>
 *
 * @see IconMacro Pro implementaci samotného makra
 */

class IconExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'icon' => [IconMacro::class, 'create'],
        ];
    }
}