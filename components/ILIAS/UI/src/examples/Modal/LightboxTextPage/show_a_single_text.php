<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Modal\LightboxTextPage;

/**
 * ---
 * description: >
 *   Example for rendering a lightbox text page modal with a single text.
 *
 * expected output: >
 *   ILIAS shows a button titled "Show Text".
 *   A click onto the button greys out ILIAS and opens the modal titled "User Agreement" including an "X" glyph on the
 *   right top and also a text.
 *   You can leave the modal by hitting the ESC key, clicking onto the greyed out ILIAS in the background outside of the
 *   modal or by clicking "X".
 * ---
 */
function show_a_single_text()
{
    global $DIC;
    $factory = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();
    $page = $factory->modal()->lightboxTextPage('Some text content you have to agree on!', 'User Agreement');
    $modal = $factory->modal()->lightbox($page);
    $button = $factory->button()->standard('Show Text', '')
        ->withOnClick($modal->getShowSignal());

    return $renderer->render([$button, $modal]);
}
