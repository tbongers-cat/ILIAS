<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Listing\Ordered;

/**
 * ---
 * description: >
 *   Example for rendering an ordered list.
 *
 * expected output: >
 *   ILIAS shows a list in the following format:
 *
 *   1. Point 1
 *   2. Point 2
 *   3. Point 3
 * ---
 */
function base()
{
    //Init Factory and Renderer
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    //Generate List
    $ordered = $f->listing()->ordered(
        ["Point 1","Point 2","Point 3"]
    );

    //Render
    return $renderer->render($ordered);
}
