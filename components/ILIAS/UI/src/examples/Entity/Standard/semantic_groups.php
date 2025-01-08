<?php

declare(strict_types=1);

namespace ILIAS\UI\Examples\Entity\Standard;

/**
 * ---
 * expected output: >
 *   ILIAS shows the rendered Component.
 * ---
 */
function semantic_groups()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $entity = $f->entity()->standard('Primary Identifier', 'Secondary Identifier')
        ->withBlockingAvailabilityConditions($f->legacy()->content('Blocking Conditions'))
        ->withFeaturedProperties($f->legacy()->content('Featured_properties'))
        ->withPersonalStatus($f->legacy()->content('Personal Status'))
        ->withMainDetails($f->legacy()->content('Main Details'))
        ->withAvailability($f->legacy()->content('Availability'))
        ->withDetails($f->legacy()->content('Details'))
        ->withReactions($f->button()->tag('reaction', '#'))
        ->withPrioritizedReactions($f->symbol()->glyph()->like())
        ->withActions($f->button()->shy('action', '#'))
    ;

    return $renderer->render($entity);
}
