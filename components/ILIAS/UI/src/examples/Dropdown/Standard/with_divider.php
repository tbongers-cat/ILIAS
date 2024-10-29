<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Dropdown\Standard;

/**
 * ---
 * description: >
 *   Example for rendering a dropdown with dividers
 *
 * expected output: >
 *   ILIAS shows a button titled "Actions" and a triangle pointing to the bottom. Clicking the button will open a
 *   dropdown menu with the entries "ILIAS" and "GitHub" rendered as shy buttons. The entries are divided by a fine line.
 *   Clicking the entries will open the appropriate website in the same browser window.
 * ---
 */
function with_divider()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $items = array(
        $f->button()->shy("ILIAS", "https://www.ilias.de"),
        $f->divider()->horizontal(),
        $f->button()->shy("GitHub", "https://www.github.com")
    );
    return $renderer->render($f->dropdown()->standard($items)->withLabel("Actions"));
}
