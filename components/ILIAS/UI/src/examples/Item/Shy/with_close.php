<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Item\Shy;

/**
 * ---
 * description: >
 *   Example for rendering a shy item with a close function.
 *
 * expected output: >
 *   ILIAS shows a box highlighted white and including the text "Test shy Item". Additionally a "X" for closing the item
 *   is displayed on the right side. If you click onto the "X" nothing will happen. This example only serves to show you
 *   the rendering of that close button.
 * ---
 */
function with_close()
{
    global $DIC;

    return $DIC->ui()->renderer()->render(
        $DIC->ui()->factory()->item()->shy('Test shy Item')->withClose(
            $DIC->ui()->factory()->button()->close()
        )
    );
}
