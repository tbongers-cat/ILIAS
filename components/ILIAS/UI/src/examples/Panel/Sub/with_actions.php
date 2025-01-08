<?php

declare(strict_types=1);

namespace ILIAS\UI\Examples\Panel\Sub;

/**
 * ---
 * description: >
 *   Example for rendering a sub panel with actions.
 *
 * expected output: >
 *   ILIAS shows a Panel including a large title "Panel Title" and a sub panel as content. The sub panel is titled
 *   "Sub Panel Title" and owns a text content "Some Content". Additionally the sub panel displays a triangle menu symbol
 *   pointing down. You can open the menu. It shows links which are pointing to ilias.de and GitHub.
 * ---
 */
function with_actions()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $actions = $f->dropdown()->standard(array(
        $f->button()->shy("ILIAS", "https://www.ilias.de"),
        $f->button()->shy("GitHub", "https://www.github.com")
    ));

    $block = $f->panel()->standard(
        "Panel Title",
        $f->panel()->sub("Sub Panel Title", $f->legacy()->content("Some Content"))->withActions($actions)
    );

    return $renderer->render($block);
}
