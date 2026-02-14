<?php
declare(strict_types=1);

namespace App\Core\Latte;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * IconMacro - Implementace {icon} makra pro Latte
 *
 * Zpracovává syntax makra a generuje PHP kód pro vkládání SVG ikon.
 * Funguje ve dvou fázích:
 * 1. Parsing (create) - Parsuje syntax {icon 'name', class: '...'}
 * 2. Code generation (print) - Generuje PHP kód, který vytvoří HTML s SVG
 *
 * Podporované formáty:
 * - {icon 'user'} - Základní ikona
 * - {icon 'user', class: 'icon--large'} - S CSS třídou
 * - {icon 'user', class: 'icon--large icon--blue'} - Více tříd
 * - {icon 'logo', sprite: 'brand-icons.svg'} - Jiný sprite soubor
 * - {icon $iconName} - Dynamická hodnota
 *
 * Proces:
 * 1. Latte najde {icon ...} v šabloně
 * 2. create() naparsuje název ikony a volitelné parametry do AST nodů
 * 3. print() vygeneruje PHP kód který:
 *    - Získá cestu k sprite souboru přes $this->global->assetRegistry
 *    - Sestaví HTML: <span class="icon"><svg><use href="...#icon-user" /></svg></span>
 * 4. Při renderingu se načte verzovaná cesta ze sprite souboru
 * 5. HTML se automaticky escapuje pro bezpečnost
 *
 * Metody:
 * - create(Tag) - Statická metoda pro parsing makra (volá Latte)
 * - print(PrintContext) - Generuje PHP kód pro renderování
 * - getIterator() - Vrací child nodes pro Latte AST
 *
 * Vlastnosti:
 * - $iconName (Node) - Název ikony (např. "user", "arrow-right")
 * - $args (ArrayNode|null) - Volitelné argumenty (class, sprite)
 *
 * @see IconExtension Pro registraci makra
 */

class IconMacro extends StatementNode
{
    public Node $iconName;
    public ?ArrayNode $args = null;

    public static function create(Tag $tag): static
    {
        // Nastavení výstupu makra
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();

        $node = new static;

        // Parse název ikony (povinné)
        $node->iconName = $tag->parser->parseUnquotedStringOrExpression();

        // Parse argumenty (volitelné) - class, sprite
        $stream = $tag->parser->stream;
        if ($stream->tryConsume(',')) {
            $node->args = $tag->parser->parseArguments();
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        // Získáme kód - PŘESNĚ jako v LinkMacro
        $iconNameCode = $this->iconName->print($context);

        // Args - pokud nejsou, dáme prázdné pole
        $argsCode = $this->args
            ? $this->args->print($context)
            : '[]';

        // Vygenerujeme inline PHP kód - všechno v jednom echo
        return $context->format(
            'echo (function($name, $args) { ' .
            '$sprite = $args["sprite"] ?? "images/icons.svg"; ' .
            '$url = $this->global->assets->resolve($sprite, [], false)->url; ' .
            '$class = isset($args["class"]) ? " " . $args["class"] : ""; ' .
            'return sprintf(' .
            '"<span class=\"icon%s\"><svg><use href=\"%s#%s\" /></svg></span>", ' .
            'htmlspecialchars($class, ENT_QUOTES, "UTF-8"), ' .
            'htmlspecialchars($url, ENT_QUOTES, "UTF-8"), ' .
            'htmlspecialchars($name, ENT_QUOTES, "UTF-8")' .
            '); ' .
            '})(%raw, %raw) %line;',
            $iconNameCode,
            $argsCode,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->iconName;
        if ($this->args) {
            yield $this->args;
        }
    }
}