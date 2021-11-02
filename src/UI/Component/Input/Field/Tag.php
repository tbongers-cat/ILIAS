<?php declare(strict_types=1);

namespace ILIAS\UI\Component\Input\Field;

use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\Input\Field\FormInputInternal;
use InvalidArgumentException;

/**
 * Interface Tag
 *
 * This describes Tag Inputs
 *
 * @package ILIAS\UI\Component\Input\Field
 */
interface Tag extends FormInput, FormInputInternal
{
    /**
     * @return string[] of tags such as [ 'Interesting', 'Boring', 'Animating', 'Repetitious' ]
     */
    public function getTags() : array;

    /**
     * Get an input like this, but decide whether the user can provide own
     * tags or not. (Default: Allowed)
     */
    public function withUserCreatedTagsAllowed(bool $extendable) : Tag;

    /**
     * @see withUserCreatedTagsAllowed
     * @return bool Whether the user is allowed to input more
     * options than the given.
     */
    public function areUserCreatedTagsAllowed() : bool;

    /**
     * Get an input like this, but change the amount of characters the
     * user has to provide before the suggestions start (Default: 1)
     *
     * @param int $characters defaults to 1
     * @throws InvalidArgumentException
     */
    public function withSuggestionsStartAfter(int $characters) : Tag;

    /**
     * @see withSuggestionsStartAfter
     */
    public function getSuggestionsStartAfter() : int;

    /**
     * Get an input like this, but limit the amount of characters one tag can be. (Default: unlimited)
     */
    public function withTagMaxLength(int $max_length) : Tag;

    /**
     * @see withTagMaxLength
     */
    public function getTagMaxLength() : int;

    /**
     * Get an input like this, but limit the amount of tags a user can select or provide. (Default: unlimited)
     */
    public function withMaxTags(int $max_tags) : Tag;


    /**
     * @see withMaxTags
     */
    public function getMaxTags() : int;


    // Events

    public function withAdditionalOnTagAdded(Signal $signal) : Tag;

    public function withAdditionalOnTagRemoved(Signal $signal) : Tag;
}
