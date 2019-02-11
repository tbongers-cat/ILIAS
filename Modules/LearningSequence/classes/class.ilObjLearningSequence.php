<?php

declare(strict_types=1);

/**
 * Class ilObjLearningSequence
 *
 */
class ilObjLearningSequence extends ilContainer
{
	const OBJ_TYPE = 'lso';

	const E_CREATE = 'create';
	const E_UPDATE = 'update';
	const E_DELETE = 'delete';

	/**
	 * @var ilLSItemsDB
	 */
	protected $items_db;

	/**
	 * @var ilLSPostConditionDB
	 */
	protected $conditions_db;

	/**
	 * @var ilLearnerProgressDB
	 */
	protected $learner_progress_db;

	/**
	 * @var ilLearningSequenceParticipant
	 */
	protected $ls_participant;

	/**
	 * @var ilLearningSequenceSettings
	 */
	protected $ls_settings;

	/**
	 * @var ilLearningSequenceFileSystem
	 */
	protected $ls_file_system;

	/**
	 * @var ilLSStateDB
	 */
	protected $state_db;

	/**
	 * @var LSRoles
	 */
	protected $ls_roles;

	/*
	 * @var ilLearningSequenceSettingsDB
	 */
	protected $settings_db;

	/*
	 * @var ilLearningSequenceSettingsDB
	 */
	protected $activation_db;

	/*
	 * @var ilLearningSequenceActivation
	 */
	protected $ls_activation;

	/**
	 * @var LSItemOnlineStatus
	 */
	protected $ls_item_online_status;

	public function __construct(int $id = 0, bool $call_by_reference = true)
	{
		global $DIC;

		$this->type = self::OBJ_TYPE;
		$this->lng = $DIC['lng'];
		$this->ctrl = $DIC['ilCtrl'];
		$this->user = $DIC['ilUser'];
		$this->tree = $DIC['tree'];
		$this->ui_factory = $DIC['ui.factory'];
		$this->ui_renderer = $DIC["ui.renderer"];
		$this->kiosk_mode_service = $DIC['service.kiosk_mode'];
		$this->template = $DIC['tpl'];
		$this->database = $DIC['ilDB'];
		$this->log = $DIC["ilLoggerFactory"]->getRootLogger();
		$this->rbacadmin = $DIC['rbacadmin'];
		$this->rbacreview = $DIC['rbacreview'];
		$this->app_event_handler = $DIC['ilAppEventHandler'];
		$this->filesystem = $DIC['filesystem'];
		$this->ilias = $DIC['ilias'];
		$this->il_settings = $DIC['ilSetting'];
		$this->il_news = $DIC->news();

		$this->data_factory = new \ILIAS\Data\Factory();

		parent::__construct($id, $call_by_reference);
	}

	static public function getInstanceByRefId(int $ref_id)
	{
		return ilObjectFactory::getInstanceByRefId($ref_id, false);
	}

	public function read()
	{
		$this->getLSSettings();
		if($this->getRefId()) {
			$this->getLSActivation();
		}
		parent::read();
	}

	public function create(): int
	{
		$id = parent::create();
		if (!$id) {
			return false;
		}
		$this->raiseEvent(self::E_CREATE);

		return (int)$this->getId();
	}

	public function update(): bool
	{
		if (!parent::update()) {
			return false;
		}
		$this->raiseEvent(self::E_UPDATE);

/*
		if ($this->getLSSettings()->getIsOnline()) { //TODO!
			$this->announceLSOOnline();
		}
*/

		return true;
	}

	public function delete(): bool
	{
		if (!parent::delete()) {
			return false;
		}

		ilLearningSequenceParticipants::_deleteAllEntries($this->getId());
		$this->getSettingsDB()->delete((int)$this->getId());
		$this->getStateDB()->deleteFor((int)$this->getRefId());
		$this->getActivationDB()->deleteForRefId((int)$this->getRefId());

		$this->raiseEvent(self::E_DELETE);

		return true;
	}

	protected function raiseEvent(string $event_type)
	{
		$this->app_event_handler->raise(
			'Modules/LearningSequence',
			$event_type,
			array(
				'obj_id' => $this->getId(),
				'appointments' => null
			)
		);
	}

	public function cloneObject($target_id, $copy_id = 0, $omit_tree = false)
	{
		$new_obj = parent::cloneObject($target_id, $copy_id, $omit_tree);

		$this->cloneAutoGeneratedRoles($new_obj);
		$this->cloneMetaData($new_obj);
		$this->cloneSettings($new_obj);
		$this->cloneLPSettings((int)$new_obj->getId());

		$new_obj->addMember((int)$this->user->getId(), $new_obj->getDefaultAdminRole());

		return $new_obj;
	}


	protected function cloneAutoGeneratedRoles(ilObjLearningSequence $new_obj): bool
	{
		$admin = $this->getDefaultAdminRole();
		$new_admin = $new_obj->getDefaultAdminRole();

		if(!$admin || !$new_admin || !$this->getRefId() || !$new_obj->getRefId()) {
			$this->log->write(__METHOD__.' : Error cloning auto generated role: il_lso_admin');
		}

		$this->rbacadmin->copyRolePermissions($admin,$this->getRefId(),$new_obj->getRefId(),$new_admin,true);
		$this->log->write(__METHOD__.' : Finished copying of role lso_admin.');

		$member = $this->getDefaultMemberRole();
		$new_member = $new_obj->getDefaultMemberRole();

		if(!$member || !$new_member) {
			$this->log->write(__METHOD__.' : Error cloning auto generated role: il_lso_member');
		}

		$this->rbacadmin->copyRolePermissions($member,$this->getRefId(),$new_obj->getRefId(),$new_member,true);
		$this->log->write(__METHOD__.' : Finished copying of role lso_member.');

		return true;
	}

	protected function cloneSettings(ilObjLearningSequence $new_obj)
	{
		$source = $this->getLSSettings();
		$target = $new_obj->getLSSettings();

		foreach ($source->getUploads() as $key => $upload_info) {
			$target = $target->withUpload($upload_info, $key);
		}

		foreach ($source->getDeletions() as $deletion) {
			$target = $target->withDeletion($deletion);
		}

		$target = $target
			->withAbstract($source->getAbstract())
			->withExtro($source->getExtro())
			->withAbstractImage($source->getAbstractImage())
			->withExtroImage($source->getExtroImage())
		;

		$new_obj->updateSettings($target);
	}

	protected function cloneLPSettings(int $obj_id)
	{
		$lp_settings = new ilLPObjSettings($this->getId());
		$lp_settings->cloneSettings($obj_id);
	}

	protected function getSettingsDB(): ilLearningSequenceSettingsDB
	{
		if (!$this->settings_db) {
			$fs =$this->getLSFileSystem();
			$this->settings_db = new ilLearningSequenceSettingsDB(
				$this->database,
				$fs
			);
		}
		return $this->settings_db;
	}

	protected function getActivationDB(): ilLearningSequenceActivationDB
	{
		if (!$this->activation_db) {
			$this->activation_db = new ilLearningSequenceActivationDB(
				$this->database
			);
		}
		return $this->activation_db;
	}

	public function getLSActivation(): ilLearningSequenceActivation
	{
		if (!$this->ls_activation) {
			$this->ls_activation = $this->getActivationDB()->getActivationForRefId((int)$this->getRefId());
		}

		return $this->ls_activation;
	}

	public function updateActivation(ilLearningSequenceActivation $settings)
	{
		$this->getActivationDB()->store($settings);
		$this->ls_activation = $settings;
	}

	public function getLSFileSystem()
	{
		if (!$this->ls_file_system) {
			$this->ls_file_system = new ilLearningSequenceFilesystem();
		}
		return $this->ls_file_system;
	}

	public function getLSSettings(): ilLearningSequenceSettings
	{
		if (!$this->ls_settings) {
			$this->ls_settings = $this->getSettingsDB()->getSettingsFor((int)$this->getId());
		}

		return $this->ls_settings;
	}

	public function updateSettings(ilLearningSequenceSettings $settings)
	{
		$this->getSettingsDB()->store($settings);
		$this->ls_settings = $settings;
	}

	protected function getLSItemsDB(): ilLSItemsDB
	{
		if (!$this->items_db) {
			$this->items_db = new ilLSItemsDB(
				$this->tree,
				ilContainerSorting::_getInstance($this->getId()),
				$this->getPostConditionDB(),
				$this->getLSItemOnlineStatus()
			);
		}

		return $this->items_db;
	}

	protected function getPostConditionDB(): ilLSPostConditionDB
	{
		if (!$this->conditions_db) {
			$this->conditions_db = new ilLSPostConditionDB($this->database);
		}

		return $this->conditions_db;
	}

	protected function getLSItemOnlineStatus(): LSItemOnlineStatus
	{
		if (!$this->ls_item_online_status) {
			$this->ls_item_online_status = new LSItemOnlineStatus();
		}

		return $this->ls_item_online_status;
	}

	public function getLSParticipants(): ilLearningSequenceParticipants
	{
		if (!$this->ls_participant) {
			$this->ls_participant = new ilLearningSequenceParticipants(
				(int)$this->getId(),
				$this->log,
				$this->app_event_handler,
				$this->il_settings
			);
		}

		return $this->ls_participant;
	}


	public function getLSAccess(): ilObjLearningSequenceAccess
	{
		if (is_null($this->ls_access)) {
			$this->ls_access = new ilObjLearningSequenceAccess();
		}

		return $this->ls_access;
	}

	/**
	 * Get a list of LSItems
	 */
	public function getLSItems(): array
	{
		$db = $this->getLSItemsDB();
		return $db->getLSItems((int)$this->getRefId());
	}

	/**
	 * Update LSItems
	 * @param LSItem[]
	 */
	public function storeLSItems(array $ls_items)
	{
		$db = $this->getLSItemsDB();
		$db->storeItems($ls_items);
	}

	/**
	 * Delete post conditions for ref ids.
	 * @param int[]
	 */
	public function deletePostConditionsForSubObjects(array $ref_ids)
	{
		$rep_utils = new ilRepUtil();
		$rep_utils->deleteObjects($this->getRefId(), $ref_ids);
		$db = $this->getPostConditionDB();
		$db->delete($ref_ids);
	}

	/**
	 * @return array<"value" => "option_text">
	 */
	public function getPossiblePostConditions(): array
	{
		return LSPostConditionTypesDB::getAvailableTypes();
	}

	protected function getLearnerProgressDB(): ilLearnerProgressDB
	{
		if(! $this->learner_progress_db) {
			$state_db = $this->getStateDB();
			$this->learner_progress_db = new ilLearnerProgressDB(
				$state_db,
				$this->access
			);
		}

		return $this->learner_progress_db;
	}

	public function getStateDB(): ilLSStateDB
	{
		if (!$this->state_db) {
			$this->state_db = new ilLSStateDB($this->database);
		}

		return $this->state_db;
	}

	/**
	 * Get a list of LSLearnerItems
	 */
	public function getLSLearnerItems(int $usr_id): array
	{
		$db = $this->getLearnerProgressDB();
		return $db->getLearnerItems($usr_id, $this->getRefId(), $this->getLSItems());
	}

	public function getLSRoles(): ilLearningSequenceRoles
	{
		if (!$this->ls_roles) {
			$this->ls_roles = new ilLearningSequenceRoles(
				$this,
				$this->getLSParticipants(),
				$this->ctrl,
				$this->rbacadmin,
				$this->rbacreview,
				$this->database,
				$this->user
			);
		}
		return $this->ls_roles;
	}

	/**
	 * Get ref-id of the last item the user touched
	 */
	public function getCurrentItemForLearner(int $usr_id): int
	{
		$db =  $this->getStateDB();
		$current = $db->getCurrentItemsFor($this->getRefId(), [$usr_id]);
		$ref_id = $current[$usr_id];

		if($ref_id < 0) {
			$ref_id = 0;
		}

		return $ref_id;
	}

	/**
	 * @param LSLearnerItem[] 	$items
	 */
	public function getCurriculumBuilder(array $items, LSUrlBuilder $url_builder=null): ilLSCurriculumBuilder
	{
		return new ilLSCurriculumBuilder(
			$items,
			$this->ui_factory,
			$this->lng,
			ilLSPlayer::LSO_CMD_GOTO,
			$url_builder
		);
	}

	public function getUrlBuilder(string $player_url): LSUrlBuilder
	{
		$player_url = $this->data_factory->uri(ILIAS_HTTP_PATH .'/'	.$player_url);
		return new LSUrlBuilder($player_url);
	}


	/**
	 * factors the player
	 */
	public function getSequencePlayer($gui, string $player_command, int $usr_id): ilLSPlayer
	{
		$lso_ref_id = $this->getRefId();
		$lso_title = $this->getTitle();

		$player_url = $this->ctrl->getLinkTarget($gui, $player_command, '', false, false);
		$items = $this->getLSLearnerItems($usr_id);
		$url_builder = $this->getUrlBuilder($player_url);

		$curriculum_builder = $this->getCurriculumBuilder(
			$items,
			$url_builder
		);

		$state_db = $this->getStateDB();

		$control_builder = new LSControlBuilder(
			$this->ui_factory,
			$url_builder,
			$this->lng
		);

		$view_factory = new ilLSViewFactory(
			$this->kiosk_mode_service,
			$this->lng,
			$this->access
		);

		$kiosk_renderer = $this->getKioskRenderer($url_builder);

		return new ilLSPlayer(
			$lso_ref_id,
			$lso_title,
			$usr_id,
			$items,
			$state_db,
			$control_builder,
			$url_builder,
			$curriculum_builder,
			$view_factory,
			$kiosk_renderer,
			$this->ui_factory
		);
	}

	protected function getKioskRenderer(LSUrlBuilder $url_builder)
	{
		if (!$this->kiosk_renderer) {
			$kiosk_template = new ilTemplate("tpl.kioskpage.html", true, true, 'Modules/LearningSequence');

			$toc_gui = new ilLSTOCGUI($url_builder, $this->template, $this->ctrl);
			$loc_gui = new ilLSLocatorGUI($url_builder, $this->ui_factory);

			$window_title = $this->il_settings->get('short_inst_name');
			if($window_title === false) {
				$window_title = 'ILIAS';
			}

			$this->kiosk_renderer = new ilKioskPageRenderer(
				$this->template,
				$this->ui_renderer,
				$kiosk_template,
				$toc_gui,
				$loc_gui,
				$window_title
			);
		}

		return $this->kiosk_renderer;
	}

	/**
	 * Get mail to members type
	 * @return int
	 */
	public function getMailToMembersType()
	{
		return $this->mail_members;
	}

	/**
	 * Goto target learning sequence.
	 *
	 * @param int $target
	 * @param string $add
	 */
	public static function _goto($target, $add = "")
	{
		global $DIC;

		$ilAccess = $DIC['ilAccess'];
		$ilErr = $DIC['ilErr'];
		$lng = $DIC['lng'];
		$ilUser = $DIC['ilUser'];

		if (substr($add,0,5) == 'rcode') {
			if ($ilUser->getId() == ANONYMOUS_USER_ID) {
				// Redirect to login for anonymous
				ilUtil::redirect(
					"login.php?target=".$_GET["target"]."&cmd=force_login&lang=".
					$ilUser->getCurrentLanguage()
				);
			}

			// Redirects to target location after assigning user to learning sequence
			ilMembershipRegistrationCodeUtils::handleCode(
				$target,
				ilObject::_lookupType(ilObject::_lookupObjId($target)),
				substr($add,5)
			);
		}

		if ($add == "mem" && $ilAccess->checkAccess("manage_members", "", $target)) {
			ilObjectGUI::_gotoRepositoryNode($target, "members");
		}

		if ($ilAccess->checkAccess("read", "", $target)) {
			ilObjectGUI::_gotoRepositoryNode($target);
		} else {
			// to do: force flat view
			if ($ilAccess->checkAccess("visible", "", $target)) {
				ilObjectGUI::_gotoRepositoryNode($target, "infoScreenGoto");
			} else {
				if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID)) {
					ilUtil::sendFailure(
						sprintf(
							$lng->txt("msg_no_perm_read_item"),
							ilObject::_lookupTitle(ilObject::_lookupObjId($target))
						),
						true
					);
					ilObjectGUI::_gotoRepositoryRoot();
				}
			}
		}

		$ilErr->raiseError($lng->txt("msg_no_perm_read"), $ilErr->FATAL);
	}

	public function getMembersObject()
	{
		return $this->getLSParticipants();
	}

	public function isMember(int $usr_id)
	{
		$part = $this->getLSParticipants();
		return $part->isMember($usr_id);
	}

	public function isCompletedByUser(int $usr_id): bool
	{
		$tracking_active = ilObjUserTracking::_enabledLearningProgress();
		$user_completion = ilLPStatus::_hasUserCompleted($this->getId(), $usr_id);
		return ($tracking_active && $user_completion);
	}

	public function getShowMembers()
	{
		return $this->getLSSettings()->getMembersGallery();
	}

	public function userMayUnparticipate(): bool
	{
		return $this->access->checkAccess('unparticipate', '', $this->getRefId());
	}

	public function userMayJoin(): bool
	{
		return $this->access->checkAccess('participate', '', $this->getRefId());
	}

	protected function announceLSOOnline()
	{
		$ns = $this->il_news;
		$context = $ns->contextForRefId((int)$this->getRefId());
		$item = $ns->item($context);
		$item->setContentIsLangVar(true);
		$item->setContentTextIsLangVar(true);
		$item->setTitle("lso_news_online_title");
		$item->setContent("lso_news_online_txt");
		$news_id = $ns->data()->save($item);
	}


	/***************************************************************************
	* Role Stuff
	***************************************************************************/
	public function getLocalLearningSequenceRoles(bool $translate = false): array
	{
		return $this->getLSRoles()->getLocalLearningSequenceRoles($translate);
	}

	public function getDefaultMemberRole(): int
	{
		return $this->getLSRoles()->getDefaultMemberRole();
	}

	public function getDefaultAdminRole()
	{
		return $this->getLSRoles()->getDefaultAdminRole();
	}

	public function addMember($user_id, $mem_role): bool
	{
		return $this->getLSRoles()->addLSMember($user_id, $mem_role);
	}

	public function join(int $user_id)
	{
		$member_role = $this->getDefaultMemberRole();
		return $this->getLSRoles()->join($user_id, $member_role);
	}

	public function leaveLearningSequence()
	{
		return $this->getLSRoles()->leaveLearningSequence();
	}

	public function getLearningSequenceMemberIds()
	{
		return $this->getLSRoles()->getLearningSequenceMemberIds();
	}

	public function leave($a_user_id)
	{
		return $this->getLSRoles()->leave($a_user_id);
	}

	public function getLearningSequenceMemberData($a_mem_ids, $active = 1)
	{
		return $this->getLSRoles()->getLearningSequenceMemberData($a_mem_ids, $active);
	}

	public function getLearningSequenceAdminIds($a_grpId = "")
	{
		return $this->getLSRoles()->getLearningSequenceAdminIds();
	}

	public function getDefaultLearningSequenceRoles($a_grp_id="")
	{
		return $this->getLSRoles()->getDefaultLearningSequenceRoles($a_grp_id);
	}

	public function initDefaultRoles()
	{
		return $this->getLSRoles()->initDefaultRoles();
	}

	public function readMemberData(array $user_ids, array $columns = null)
	{
		return $this->getLsRoles()->readMemberData($user_ids, $columns);
	}

}
