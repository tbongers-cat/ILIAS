<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Standard;

/**
 * ---
 * description: >
 *   Example for rendering a standard button with tooltips
 *
 * expected output: >
 *   ILIAS shows an active button titled "Goto ILIAS". Hovering over the button will show you a tooltip with the following
 *   lines: "tooltip: ilias" and "tooltip: learning management system".
 * ---
 */
function with_tooltip()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $button = $f->button()
        ->standard("Goto ILIAS", "http://www.ilias.de")
        ->withHelpTopics(
            ...$f->helpTopics("ilias", "learning management system")
        );

    return $renderer->render($button);
}
