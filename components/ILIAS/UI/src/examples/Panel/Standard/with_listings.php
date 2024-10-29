<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Panel\Standard;

/**
 * ---
 * description: >
 *   Example for rendering a standard panel with listings.
 *
 * expected output: >
 *   ILIAS shows a panel with a large title "Panel Title" and two lists in the following format:
 *
 *   1. item 1
 *   2. item 2
 *   3. item 3
 *   - item 1
 *   - item 2
 *   - item 3
 * ---
 */
function with_listings()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $content = array(
        $f->listing()->ordered(array("item 1","item 2","item 3")),
        $f->listing()->unordered(array("item 1","item 2","item 3"))
    );

    $panel = $f->panel()->standard("Panel Title", $content);

    return $renderer->render($panel);
}
