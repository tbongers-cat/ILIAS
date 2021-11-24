<?php declare(strict_types=1);

/* Copyright (c) 2017 Alex Killing <killing@leifos.de> Extended GPL, see docs/LICENSE */

namespace ILIAS\UI\Implementation\Component\Item;

use ilDateTime;
use ILIAS\UI\Component\Item;
use ILIAS\UI\Component\Symbol\Icon\Icon;
use ilObjUser;

class Factory implements Item\Factory
{
    /**
     * @inheritdoc
     */
    public function standard($title) : Item\Standard
    {
        return new Standard($title);
    }

    /**
     * @inheritdoc
     */
    public function contribution(string $content, ?ilObjUser $user = null, ?ilDateTime $datetime = null) : Item\Contribution
    {
        return new Contribution($content, $user, $datetime);
    }

    /**
     * @inheritdoc
     */
    public function group(string $title, array $items) : Item\Group
    {
        return new Group($title, $items);
    }

    /**
     * @inheritdoc
     */
    public function notification($title, Icon $icon) : Item\Notification
    {
        return new Notification($title, $icon);
    }
}
