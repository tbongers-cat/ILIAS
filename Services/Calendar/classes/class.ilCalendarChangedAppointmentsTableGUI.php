<?php
/*
        +-----------------------------------------------------------------------------+
        | ILIAS open source                                                           |
        +-----------------------------------------------------------------------------+
        | Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
        |                                                                             |
        | This program is free software; you can redistribute it and/or               |
        | modify it under the terms of the GNU General Public License                 |
        | as published by the Free Software Foundation; either version 2              |
        | of the License, or (at your option) any later version.                      |
        |                                                                             |
        | This program is distributed in the hope that it will be useful,             |
        | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
        | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
        | GNU General Public License for more details.                                |
        |                                                                             |
        | You should have received a copy of the GNU General Public License           |
        | along with this program; if not, write to the Free Software                 |
        | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
        +-----------------------------------------------------------------------------+
*/

/**
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 * @ingroup ServicesCalendar
 */
class ilCalendarChangedAppointmentsTableGUI extends ilTable2GUI
{
    private int $cat_id = 0;
    private bool $is_editable = false;
    private ilObjUser $user;

    public function __construct(?object $a_parent_obj, string $a_parent_cmd)
    {
        global $DIC;

        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->lng->loadLanguageModule('dateplaner');
        $this->ctrl = $DIC->ctrl();

        $this->setId('calinbox');

        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setFormName('appointments');
        $this->addColumn($this->lng->txt('date'), 'begin', "30%");
        $this->addColumn($this->lng->txt('title'), 'title', "40%");
        #$this->addColumn($this->lng->txt('cal_duration'),'duration',"15%");
        $this->addColumn($this->lng->txt('cal_recurrences'), 'frequence', "15%");
        $this->addColumn($this->lng->txt('last_update'), 'last_update', "15%");

        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
        $this->setRowTemplate("tpl.show_changed_appointment_row.html", "Services/Calendar");

        $this->setShowRowsSelector(true);
        $this->enable('sort');
        $this->enable('header');
        $this->enable('numinfo');

        $this->setDefaultOrderField('begin');
        $this->setDefaultOrderDirection('asc');
    }

    protected function fillRow(array $a_set): void
    {
        $this->tpl->setVariable('VAL_DESCRIPTION', $a_set['description']);

        $this->tpl->setVariable('VAL_TITLE_LINK', $a_set['title']);
        $this->ctrl->setParameterByClass('ilcalendarappointmentgui', 'app_id', $a_set['id']);
        $this->tpl->setVariable('VAL_LINK', $this->ctrl->getLinkTargetByClass('ilcalendarappointmentgui', 'edit'));

        switch ($a_set['frequence']) {
            case ilCalendarRecurrence::FREQ_DAILY:
                $this->tpl->setVariable('VAL_FREQUENCE', $this->lng->txt('cal_daily'));
                break;

            case ilCalendarRecurrence::FREQ_WEEKLY:
                $this->tpl->setVariable('VAL_FREQUENCE', $this->lng->txt('cal_weekly'));
                break;

            case ilCalendarRecurrence::FREQ_MONTHLY:
                $this->tpl->setVariable('VAL_FREQUENCE', $this->lng->txt('cal_monthly'));
                break;

            case ilCalendarRecurrence::FREQ_YEARLY:
                $this->tpl->setVariable('VAL_FREQUENCE', $this->lng->txt('cal_yearly'));
                break;

            default:
                #$this->tpl->setVariable('VAL_FREQUENCE',$this->lng->txt('cal_no_recurrence'));
                break;
        }
        if ($a_set['fullday']) {
            $date = ilDatePresentation::formatPeriod(
                new ilDate($a_set['begin'], IL_CAL_UNIX),
                new ilDate($a_set['end'], IL_CAL_UNIX)
            );
        } else {
            $date = ilDatePresentation::formatPeriod(
                new ilDateTime($a_set['begin'], IL_CAL_UNIX),
                new ilDateTime($a_set['end'], IL_CAL_UNIX)
            );
        }
        $this->tpl->setVariable('VAL_BEGIN', $date);
        $update = new ilDateTime($a_set['last_update'], IL_CAL_UNIX, $this->user->getTimeZone());
        $this->tpl->setVariable('VAL_LAST_UPDATE', ilDatePresentation::formatDate($update));
    }

    public function setAppointments(array $a_apps): void
    {
        $appointments = [];
        foreach ($a_apps as $event) {
            $entry = $event['event'];

            $rec = ilCalendarRecurrences::_getFirstRecurrence($entry->getEntryId());

            $tmp_arr['id'] = $entry->getEntryId();
            $tmp_arr['title'] = $entry->getPresentationTitle();
            $tmp_arr['description'] = $entry->getDescription();
            $tmp_arr['fullday'] = $entry->isFullday();

            $tmp_arr['begin'] = $event['dstart'];
            $tmp_arr['end'] = $event['dend'];

            $tmp_arr['duration'] = $tmp_arr['end'] - $tmp_arr['begin'];
            if ($tmp_arr['fullday']) {
                $tmp_arr['duration'] += (60 * 60 * 24);
            }
            if (!$tmp_arr['fullday'] and $tmp_arr['end'] == $tmp_arr['begin']) {
                $tmp_arr['duration'] = '';
            }

            $tmp_arr['last_update'] = $entry->getLastUpdate()->get(IL_CAL_UNIX);
            $tmp_arr['frequence'] = $rec->getFrequenceType();

            $appointments[] = $tmp_arr;
        }

        //cuts appointments array after Limit
        $appointments = array_slice($appointments, 0, $this->getLimit());
        $this->setData($appointments);
    }
}
