<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Bulky;

/**
 * ---
 * description: >
 *   Example for rendering a bulky button.
 *
 * note: >
 *   The exact look of the Bulky Buttons is mostly defined by the surrounding container.
 *
 * expected output: >
 *   ILIAS shows a button with an icon and titled "Icon". The button's size is almost as wide as the width of the box
 *   in the background. Clicking the button won't activate any actions.
 *   Additionally ILIAS shows a button with a glyph and the title "Glyph". The button's size is also almost as wide as
 *   the width of the box in the background. Clicking the button won't activate any actions.
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $ico = $f->symbol()->icon()
        ->standard('someExample', 'Example')
        ->withAbbreviation('E')
        ->withSize('medium');
    $button = $f->button()->bulky($ico, 'Icon', '#');

    $glyph = $f->symbol()->glyph()->briefcase();
    $button2 = $f->button()->bulky($glyph, 'Glyph', '#');

    $button3 = $f->button()->bulky($glyph, '', '#');
    $button4 = $f->button()->bulky($ico, '', '#');

    return $renderer->render([
        $button,
        $f->divider()->horizontal(),
        $button2,
        $f->divider()->horizontal(),
        $button3,
        $f->divider()->horizontal(),
        $button4
    ]);
}
