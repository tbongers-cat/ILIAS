<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Primary;

/**
 * ---
 * description: >
 *   Example for rendering a primary button with tooltips
 *
 * expected output: >
 *   ILIAS shows an active button titled "Goto ILIAS". Hovering over the button will show you a tooltip with the following
 *   content: "tooltip: ilias" and "tooltip: learning management system".
 * ---
 */
function with_tooltip()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();
    $primary = $f->button()->primary("Goto ILIAS", "http://www.ilias.de")
        ->withHelpTopics(
            ...$f->helpTopics("ilias", "learning management system")
        );
    return $renderer->render($primary);
}
