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

class ilDclTextRecordRepresentation extends ilDclBaseRecordRepresentation
{
    public const LINK_MAX_LENGTH = 40;

    public function getHTML(bool $link = true, array $options = []): string
    {
        $value = $this->getRecordField()->getValue();

        $ref_id = $this->http->wrapper()->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        $views = $this->getRecord()->getTable()->getVisibleTableViews($ref_id, true, $this->user->getId());

        //Property URL
        $field = $this->getField();
        if ($field->hasProperty(ilDclBaseFieldModel::PROP_URL)) {
            if (is_array($value)) {
                $link = (string) $value['link'];
                $link_value = $value['title'] ?: $this->shortenLink($link);
            } else {
                $link = $value;
                $link_value = $this->shortenLink($link);
            }

            if (substr($link, 0, 3) === 'www') {
                $link = 'https://' . $link;
            }

            if (preg_match(
                "/^[a-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i",
                $link
            )) {
                $link = "mailto:" . $link;
            } elseif (!(preg_match('~(^(news|(ht|f)tp(s?)\://){1}\S+)~i', $link))) {
                return $link;
            }

            $html = "<a rel='noopener' target='_blank' href='" . htmlspecialchars(
                $link,
                ENT_QUOTES
            ) . "'>" . htmlspecialchars($link_value, ENT_QUOTES) . "</a>";
        } elseif ($field->hasProperty(ilDclBaseFieldModel::PROP_LINK_DETAIL_PAGE_TEXT) && $link && $views !== []) {
            $view = array_shift($views);
            if ($this->http->wrapper()->query()->has('tableview_id')) {
                $tableview_id = $this->http->wrapper()->query()->retrieve('tableview_id', $this->refinery->kindlyTo()->int());
                foreach ($views as $v) {
                    if ($v->getId() === $tableview_id) {
                        $view = $tableview_id;
                        break;
                    }
                }
            }

            $this->ctrl->clearParametersByClass("ilDclDetailedViewGUI");
            $this->ctrl->setParameterByClass(
                ilDclDetailedViewGUI::class,
                'record_id',
                $this->getRecordField()->getRecord()->getId()
            );

            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'table_id', $this->getRecord()->getTableId());
            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'tableview_id', $view->getId());
            $html = '<a href="' . $this->ctrl->getLinkTargetByClass(
                ilDclDetailedViewGUI::class,
                'renderRecord'
            ) . '">' . $value . '</a>';
        } else {
            $html = (is_array($value) && isset($value['link'])) ? $value['link'] : nl2br((string) $value);
        }

        return $html;
    }

    protected function shortenLink(string $value): string
    {
        $value = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $value);
        $half = (int) ((self::LINK_MAX_LENGTH - 4) / 2);
        $value = preg_replace('/^(.{' . ($half + 1) . '})(.{4,})(.{' . $half . '})$/', '\1...\3', $value);

        return $value;
    }

    public function fillFormInput(ilPropertyFormGUI $form): void
    {
        $input_field = $form->getItemByPostVar('field_' . $this->getField()->getId());
        $raw_input = $this->getFormInput();

        $value = is_array($raw_input) ? $raw_input['link'] : $raw_input;
        $value = is_string($value) ? $value : "";
        $field_values = [];
        if ($this->getField()->getProperty(ilDclBaseFieldModel::PROP_URL)) {
            $field_values["field_" . $this->getRecordField()->getField()->getId() . "_title"] = (isset($raw_input['title'])) ? $raw_input['title'] : '';
        }

        if ($this->getField()->hasProperty(ilDclBaseFieldModel::PROP_TEXTAREA)) {
            $breaks = ["<br />"];
            $value = str_ireplace($breaks, "", $value);
        }

        $field_values["field_" . $this->getRecordField()->getField()->getId()] = $value;
        $input_field->setValueByArray($field_values);
    }
}
