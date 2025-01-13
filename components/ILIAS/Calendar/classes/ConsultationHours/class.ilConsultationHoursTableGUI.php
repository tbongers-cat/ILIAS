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

use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

/**
 * Consultation hours administration
 * @author  Stefan Meyer <meyer@leifos.com>
 * @ingroup ServicesCalendar
 */
class ilConsultationHoursTableGUI extends ilTable2GUI
{
    private int $user_id = 0;
    private UIRenderer $renderer;
    private UIFactory $uiFactory;

    public function __construct(object $a_gui, string $a_cmd, int $a_user_id)
    {
        $this->user_id = $a_user_id;

        $this->setId('chtg_' . $this->getUserId());
        parent::__construct($a_gui, $a_cmd);

        global $DIC;
        $this->renderer = $DIC->ui()->renderer();
        $this->uiFactory = $DIC->ui()->factory();

        $this->addColumn('', 'f', '1');
        $this->addColumn($this->lng->txt('appointment'), 'start');

        $this->addColumn($this->lng->txt('title'), 'title');
        $this->addColumn($this->lng->txt('cal_ch_num_bookings'), 'num_bookings');
        $this->addColumn($this->lng->txt('cal_ch_bookings'), 'participants');
        $this->addColumn($this->lng->txt('cal_ch_target_object'), 'target');
        $this->addColumn('');

        $this->setRowTemplate('tpl.ch_upcoming_row.html', 'components/ILIAS/Calendar');
        $this->setFormAction($this->ctrl->getFormAction($this->getParentObject(), $this->getParentCmd()));
        $this->setTitle($this->lng->txt('cal_ch_ch'));

        $this->enable('sort');
        $this->enable('header');
        $this->enable('numinfo');

        $this->setDefaultOrderField('start');
        $this->setSelectAllCheckbox('apps');
        $this->setShowRowsSelector(true);
        $this->addMultiCommand('edit', $this->lng->txt('edit'));
        $this->addMultiCommand('searchUsersForAppointments', $this->lng->txt('cal_ch_assign_participants'));
        $this->addMultiCommand('confirmDelete', $this->lng->txt('delete'));
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * @inheritDoc
     */
    protected function fillRow(array $a_set): void
    {
        $this->tpl->setVariable('VAL_ID', $a_set['id']);
        $this->tpl->setVariable('START', $a_set['start_p']);
        $this->tpl->setVariable('TITLE', $a_set['title']);
        $this->tpl->setVariable('NUM_BOOKINGS', $a_set['num_bookings']);

        foreach ((array) ($a_set['target_links'] ?? []) as $link) {
            $this->tpl->setCurrentBlock('links');
            $this->tpl->setVariable('TARGET', $link['title']);
            $this->tpl->setVariable('URL_TARGET', $link['link']);
            $this->tpl->parseCurrentBlock();
        }
        if ($a_set['bookings']) {
            foreach ($a_set['bookings'] as $user_id => $name) {
                $user_profile_prefs = ilObjUser::_getPreferences($user_id);
                if (($user_profile_prefs["public_profile"] ?? '') == "y") {
                    $this->tpl->setCurrentBlock('booking_with_link');
                    $this->ctrl->setParameter($this->getParentObject(), 'user', $user_id);
                    $this->tpl->setVariable(
                        'URL_BOOKING',
                        $this->ctrl->getLinkTarget($this->getParentObject(), 'showprofile')
                    );
                } else {
                    $this->tpl->setCurrentBlock('booking_without_link');
                }
                $this->ctrl->setParameter($this->getParentObject(), 'user', '');
                $this->tpl->setVariable('TXT_BOOKING', $name);
                $this->tpl->parseCurrentBlock();
            }
        }

        $this->tpl->setVariable('BOOKINGS', implode(', ', $a_set['bookings']));
        $this->ctrl->setParameter($this->getParentObject(), 'apps', $a_set['id']);

        $dropDownItems = array(
            $this->uiFactory->button()->shy(
                $this->lng->txt('edit'),
                $this->ctrl->getLinkTarget($this->getParentObject(), 'edit')
            ),
            $this->uiFactory->button()->shy(
                $this->lng->txt('cal_ch_assign_participants'),
                $this->ctrl->getLinkTargetByClass('ilRepositorySearchGUI', '')
            ),
            $this->uiFactory->button()->shy(
                $this->lng->txt('delete'),
                $this->ctrl->getLinkTarget($this->getParentObject(), 'confirmDelete')
            )
        );
        $dropDown = $this->uiFactory->dropdown()->standard($dropDownItems)
                ->withLabel($this->lng->txt('actions'));
        $this->tpl->setVariable('ACTIONS', $this->renderer->render($dropDown));
    }

    public function parse()
    {
        $data = array();
        $counter = 0;
        foreach (ilConsultationHourAppointments::getAppointments($this->getUserId()) as $app) {
            $data[$counter]['id'] = $app->getEntryId();
            $data[$counter]['title'] = $app->getTitle();
            $data[$counter]['description'] = $app->getDescription();
            $data[$counter]['start'] = $app->getStart()->get(IL_CAL_UNIX);
            $data[$counter]['start_p'] = ilDatePresentation::formatPeriod($app->getStart(), $app->getEnd());

            $booking = new ilBookingEntry($app->getContextId());

            $booked_user_ids = $booking->getCurrentBookings($app->getEntryId());
            $booked_user_ids = array_map('intval', ilUtil::_sortIds($booked_user_ids, 'usr_data', 'lastname', 'usr_id'));
            $users = array();
            $data[$counter]['participants'] = '';
            $user_counter = 0;
            foreach ($booked_user_ids as $user_id) {
                if (!$user_counter) {
                    $name = ilObjUser::_lookupName($user_id);
                    $data[$counter]['participants'] = $name['lastname'];
                }
                $users[$user_id] = ilObjUser::_lookupFullname($user_id);
                $user_counter++;
            }
            $data[$counter]['bookings'] = $users;
            $data[$counter]['num_bookings'] = $booking->getNumberOfBookings();

            $data[$counter]['group'] = '';

            // obj assignments
            $refs_counter = 0;
            $obj_ids = array_map(
                'intval',
                ilUtil::_sortIds($booking->getTargetObjIds(), 'object_data', 'title', 'obj_id')
            );
            foreach ($obj_ids as $obj_id) {
                if ($refs_counter) {
                    $data[$counter]['target'] = ilObject::_lookupTitle($obj_id);
                }

                $refs = ilObject::_getAllReferences($obj_id);
                $data[$counter]['target_links'][$refs_counter]['title'] = ilObject::_lookupTitle($obj_id);
                $data[$counter]['target_links'][$refs_counter]['link'] = ilLink::_getLink(end($refs));
                ++$refs_counter;
            }
            $counter++;
        }
        $this->setData($data);
    }
}
