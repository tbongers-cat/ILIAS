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

use ILIAS\ResourceStorage\Services;
use ILIAS\Data\DataSize;
use ILIAS\FileUpload\MimeType;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 *
 *         Use this class to get common information about a file object. if you need infos for multiple files, use the
 *         preloadData method to load all data at once.
 */
class ilObjFileInfoRepository
{
    private static array $cache = [];
    /**
     * @readonly
     */
    private Services $irss;
    /**
     * @readonly
     */
    private ilDBInterface $db;
    private array $inline_suffixes = [];

    public function __construct(bool $with_cleared_cache = false)
    {
        global $DIC;
        if ($with_cleared_cache) {
            self::$cache = [];
        }
        $this->irss = $DIC->resourceStorage();
        $this->db = $DIC->database();
        $this->inline_suffixes = $this->initInlineSuffixes();
    }

    public function getByRefId(int $ref_id): ilObjFileInfo
    {
        $object_id = ilObject2::_lookupObjectId($ref_id);

        return $this->getByObjectId($object_id);
    }

    private function initInlineSuffixes(): array
    {
        $settings = new ilSetting('file_access');
        return array_map('strtolower', explode(' ', (string) $settings->get('inline_file_extensions', '')));
    }

    public function preloadData(array $ids, bool $are_ref_ids = false): void
    {
        if ($are_ref_ids) {
            $query = "SELECT title, rid, file_id, page_count, on_click_mode
                FROM file_data 
                    JOIN object_data ON object_data.obj_id = file_data.file_id 
                    JOIN object_reference ON object_reference.obj_id = object_data.obj_id  
                WHERE rid IS NOT NULL AND " . $this->db->in(
                'ref_id',
                $ids,
                false,
                'integer'
            );
        } else {
            $query = "SELECT title, rid, file_id, page_count, on_click_mode  FROM file_data JOIN object_data ON object_data.obj_id = file_data.file_id WHERE rid IS NOT NULL AND " . $this->db->in(
                'file_id',
                $ids,
                false,
                'integer'
            );
        }

        $res = $this->db->query(
            $query
        );
        $rids = [];
        $page_counts = [];
        $object_titles = [];
        $click_modes = [];

        while ($row = $this->db->fetchObject($res)) {
            $rids[(int) $row->file_id] = $row->rid;
            $page_counts[(int) $row->file_id] = $row->page_count;
            $object_titles[(int) $row->file_id] = $row->title;
            $click_modes[(int) $row->file_id] = (int) ($row->on_click_mode ?? ilObjFile::CLICK_MODE_DOWNLOAD);
        }
        $this->irss->preload($rids);

        foreach ($rids as $file_id => $rid) {
            if (($id = $this->irss->manage()->find($rid)) !== null) {
                $max = $this->irss->manage()->getResource($id)->getCurrentRevision();

                $info = new ilObjFileInfo(
                    $object_titles[$file_id] ?? $max->getTitle(),
                    $id,
                    $max->getInformation()->getTitle(),
                    $max->getInformation()->getSuffix(),
                    in_array(strtolower($max->getInformation()->getSuffix()), $this->inline_suffixes, true),
                    $click_modes[$file_id] === ilObjFile::CLICK_MODE_DOWNLOAD,
                    $max->getVersionNumber(),
                    $max->getInformation()->getCreationDate(),
                    in_array(strtolower($max->getInformation()->getMimeType()), [
                        MimeType::APPLICATION__ZIP,
                        MimeType::APPLICATION__X_ZIP_COMPRESSED
                    ], true),
                    $max->getInformation()->getMimeType(),
                    new DataSize($max->getInformation()->getSize() ?? 0, DataSize::Byte),
                    $page_counts[$file_id] === null ? null : (int) $page_counts[$file_id]
                );

                self::$cache[$file_id] = $info;
            }
        }
    }

    public function getByObjectId(int $object_id): ilObjFileInfo
    {
        if (isset(self::$cache[$object_id])) {
            return self::$cache[$object_id];
        }

        $this->preloadData([$object_id]);

        return self::$cache[$object_id] ?? new ilObjFileInfo(
            'Unknown',
            null,
            'Unknown',
            '',
            false,
            false,
            0,
            new DateTimeImmutable(),
            false,
            '',
            new DataSize(0, DataSize::Byte),
            null
        );
    }

}
