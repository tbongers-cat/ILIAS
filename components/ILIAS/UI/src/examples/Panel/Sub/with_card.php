<?php

declare(strict_types=1);

namespace ILIAS\UI\Examples\Panel\Sub;

/**
 * ---
 * description: >
 *   Example for rendering a sub panel with a card.
 *
 * expected output: >
 *   ILIAS shows a Panel including a large title "Panel Title" and a sub panel as content. The sub panel is titled
 *   "Sub Panel Title" and owns a text content "Some Content". Additionally it displays a card titled "Card Heading" and
 *   including the content "Card Content". On bigger desktops the card is displayed on the right side. On smaller desktops
 *   the card is displayed below the sub panel text content.
 * ---
 */
function with_card()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $block = $f->panel()->standard(
        "Panel Title",
        $f->panel()->sub("Sub Panel Title", $f->legacy()->content("Some Content"))
            ->withFurtherInformation($f->card()->standard("Card Heading")->withSections(array($f->legacy()->content("Card Content"))))
    );

    return $renderer->render($block);
}
