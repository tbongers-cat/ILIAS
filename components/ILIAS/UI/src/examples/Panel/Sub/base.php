<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Panel\Sub;

/**
 * ---
 * description: >
 *   Example for rendering a sub panel.
 *
 * expected output: >
 *   ILIAS shows a Panel including a large title "Panel Title" and a sub panel as content. The sub panel is titled
 *   "Sub Panel Title" and owns a text content "Some Content".
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $block = $f->panel()->standard(
        "Panel Title",
        $f->panel()->sub("Sub Panel Title", $f->legacy()->content("Some Content"))
    );

    return $renderer->render($block);
}
