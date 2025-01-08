<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Panel\Report;

/**
 * ---
 * description: >
 *   Example for rendering a report panel.
 *
 * expected output: >
 *   ILIAS shows a panel titled "Report Title" and two sub panels as content.
 *   The first sub panel is titled "Sub Panel Title 1", displays the text "Some Content" and a card titled  "Card Heading"
 *   including it's content "Card Content".
 *   The second sub panel is titled "Sub Panel Title 2" and displays the content text "Some Content".
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $sub1 = $f->panel()->sub("Sub Panel Title 1", $f->legacy()->content("Some Content"))
            ->withFurtherInformation($f->card()->standard("Card Heading")->withSections(array($f->legacy()->content("Card Content"))));
    $sub2 = $f->panel()->sub("Sub Panel Title 2", $f->legacy()->content("Some Content"));

    $block = $f->panel()->report("Report Title", array($sub1,$sub2));

    return $renderer->render($block);
}
