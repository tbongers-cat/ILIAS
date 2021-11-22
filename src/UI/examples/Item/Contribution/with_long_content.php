<?php declare(strict_types=1);

namespace ILIAS\UI\examples\Item\Contribution;

function with_lead_icon()
{
    global $DIC;

    return $DIC->ui()->renderer()->render(
        $DIC->ui()->factory()->item()->contribution(
            'This is a test contribution of the root user in the current time. This is a little bit longer than
            the usual to test its visual presentation when the content exceed a minor amount of chars to see if this
            is still presented properly. This may affect its presentation inside a mobile or resticted view hterefore
            the presentation of a long text is necessary to test within this example to show its responsivity abover all
            views and to show the using developer, who is accessing this example for exact those information if the
            component can be used for his target purpose.',
            new \ilObjUser(6),
            new \ilDateTime(time(), IL_CAL_UNIX)
        )
    );
}
