<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Standard;

/**
 * ---
 * description: >
 *   Example for rendering an engaged standard button
 *
 * expected output: >
 *   ILIAS shows a grey, active button titled "Engaged". The button looks different from the base standard button.
 *   Clicking the button won't activate any actions.
 * ---
 */
function engaged()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $button = $f->button()->standard("Engaged Button", "#")
                                  ->withEngagedState(true);
    return $renderer->render($button);
}
