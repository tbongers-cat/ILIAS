<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Legacy\Content;

/**
 * ---
 * description: >
 *   Example for rendering a legacy box.
 *
 * expected output: >
 *   ILIAS shows a box including the text "Legacy Content".
 * ---
 */
function inside_panel()
{
    //Init Factory and Renderer
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    //Init Component
    $legacy = $f->legacy()->content("Legacy Content");
    $panel = $f->panel()->standard("Panel Title", $legacy);

    //Render
    return $renderer->render($panel);
}
