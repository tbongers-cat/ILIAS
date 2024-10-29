<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Close;

/**
 * ---
 * description: >
 *   This example is rather artificial, since the close button is only used
 *   in other components (see purpose).
 *   This examples just shows how one could render the button if implementing
 *   such a component.
 *
 * expected output: >
 *   ILIAS shows a grey box with a dark grey "X" in the right corner. Clicking the "X" won't activate any action.
 *
 * note: >
 *  In some cases, additional CSS will be required for placing the button
 *  properly by the surrounding component.
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    return $renderer->render($f->button()->close());
}
