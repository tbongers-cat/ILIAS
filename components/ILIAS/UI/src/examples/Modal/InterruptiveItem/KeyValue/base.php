<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Modal\InterruptiveItem\KeyValue;

/**
 * ---
 * description: >
 *   Example for rendering a key value interruptive item modal.
 *
 * expected output: >
 *   ILIAS shows a button titled "Show an interruptive Item".
 *   A click onto the button will grey out ILIAS, open a modal titled "My Title" and asks for confirmation to delete all
 *   contents.
 *   Two buttons "Delete" and "Cancel" will also be displayed.
 *   A click onto the button "Delete" will relaod the page.
 *   A click onto the button "Cancel" will hide the modal.
 *   You can also leave the modal by clicking onto the greyed out ILIAS in the background outside of the modal.
 * ---
 */
function base()
{
    global $DIC;
    $factory = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $message = 'Here you see some key-value interruptive items:';
    $modal = $factory->modal()->interruptive('My Title', $message, "#")
                     ->withAffectedItems(array(
                         $factory->modal()->interruptiveItem()->keyValue(
                             '10',
                             'First Key',
                             'first value'
                         ),
                         $factory->modal()->interruptiveItem()->keyValue(
                             '20',
                             'Second Key',
                             'second value'
                         ),
                         $factory->modal()->interruptiveItem()->keyValue(
                             '30',
                             'Third Key',
                             'really really long value, much much longer than any value needs to be, that should be cut off at some point'
                         )
                     ));
    $button = $factory->button()->standard('Show some key-value interruptive Items', '')
                      ->withOnClick($modal->getShowSignal());


    return $renderer->render([$button, $modal]);
}
