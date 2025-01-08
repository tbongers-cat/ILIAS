<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Legacy\Content;

/**
 * ---
 * description: >
 *   Example for rendering a legacy box with an inside panel.
 *
 * expected output: >
 *   ILIAS shows a box titled "Panel Title" and a grey background. In the lower part of the box the text "Legacy Content"
 *   on a white background is written.
 * ---
 */
function base()
{
    //Init Factory and Renderer
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    //Init Component
    $legacy = $f->legacy()->content("Legacy\Content");

    //Render
    return $renderer->render($legacy);
}
