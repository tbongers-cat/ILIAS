<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Filesystem\Stream\Streams;

/**
 * @noinspection AutoloadingIssuesInspection
 */
class ilDclFileRecordFieldModel extends ilDclBaseRecordFieldModel
{
    use ilDclFileFieldHelper;

    private const FILE_TMP_NAME = 'tmp_name';
    private const FILE_NAME = "name";
    private const FILE_TYPE = "type";

    protected \ILIAS\ResourceStorage\Services $irss;
    protected ilDataCollectionStakeholder $stakeholder;
    protected \ILIAS\FileUpload\FileUpload $upload;

    public function __construct(ilDclBaseRecordModel $record, ilDclBaseFieldModel $field)
    {
        global $DIC;
        parent::__construct($record, $field);
        $this->stakeholder = new ilDataCollectionStakeholder();
        $this->irss = $DIC->resourceStorage();
        $this->upload = $DIC->upload();
    }

    public function parseValue($value)
    {
        $file = $value;

        // Some general Request Information
        $has_record_id = $this->http->wrapper()->query()->has('record_id');
        $is_confirmed = $this->http->wrapper()->post()->has('save_confirmed');
        $has_save_confirmation = ($this->getRecord()->getTable()->getSaveConfirmation() && !$has_record_id);

        if (
            is_array($file)
            && isset($file[self::FILE_TMP_NAME])
            && $file[self::FILE_TMP_NAME] !== ""
            && (!$has_save_confirmation || $is_confirmed)
        ) {
            if ($has_save_confirmation) {
                $ilfilehash = $this->http->wrapper()->post()->retrieve(
                    'ilfilehash',
                    $this->refinery->kindlyTo()->string()
                );

                $move_file = ilDclPropertyFormGUI::getTempFilename(
                    $ilfilehash,
                    'field_' . $this->getField()->getId(),
                    $file[self::FILE_NAME],
                    $file[self::FILE_TYPE]
                );

                $file_stream = ILIAS\Filesystem\Stream\Streams::ofResource(fopen($move_file, 'rb'));
            } else {
                $move_file = $file[self::FILE_TMP_NAME];

                if (false === $this->upload->hasBeenProcessed()) {
                    $this->upload->process();
                }

                if (false === $this->upload->hasUploads()) {
                    throw new ilException($this->lng->txt('upload_error_file_not_found'));
                }

                $file_stream = Streams::ofResource(fopen($move_file, 'rb'));
            }

            $file_title = $file[self::FILE_NAME] ?? basename($move_file);

            // Storing the File to the IRSS
            $existing_value = $this->getValueForRepresentation();
            if (
                is_string($existing_value)
                && ($rid = $this->irss->manage()->find($existing_value)) !== null
            ) {
                // Append to existing RID
                $this->irss->manage()->replaceWithStream(
                    $rid,
                    $file_stream,
                    $this->stakeholder,
                    $file_title
                );
            } else {
                // Create new RID
                $rid = $this->irss->manage()->stream(
                    $file_stream,
                    $this->stakeholder,
                    $file_title
                );
            }

            return $rid->serialize();
        } else {
            // handover for save-confirmation
            if (is_array($file) && isset($file[self::FILE_TMP_NAME]) && $file[self::FILE_TMP_NAME] != "") {
                return $file;
            }
        }
        return $this->getValue();
    }

    public function setValueFromForm(ilPropertyFormGUI $form): void
    {
        if ($this->value !== null && $form->getItemByPostVar("field_" . $this->getField()->getId())->getDeletionFlag()) {
            $this->removeData();
            $this->setValue(null, true);
            $this->doUpdate();
        }
        parent::setValueFromForm($form);
    }

    public function delete(): void
    {
        if ($this->value !== null) {
            $this->removeData();
        }
        parent::delete();
    }

    protected function removeData(): void
    {
        $this->irss->manage()->remove($this->irss->manage()->find($this->value), $this->stakeholder);
    }

    public function parseExportValue($value)
    {
        return $this->valueToFileTitle($value);
    }

    public function parseSortingValue($value, bool $link = true)
    {
        return $this->valueToFileTitle($value);
    }

    public function afterClone(): void
    {
        $field = ilDclCache::getCloneOf((int) $this->getField()->getId(), ilDclCache::TYPE_FIELD);
        $record = ilDclCache::getCloneOf($this->getRecord()->getId(), ilDclCache::TYPE_RECORD);
        $record_field = ilDclCache::getRecordFieldCache($record, $field);

        if (!$record_field->getValue()) {
            return;
        }
        $current = $this->valueToCurrentRevision($record_field->getValue());
        if ($current !== null) {
            $new_rid = $this->irss->manage()->clone($current->getIdentification());
            $this->setValue($new_rid->serialize());
            $this->doUpdate();
        }
    }
}
