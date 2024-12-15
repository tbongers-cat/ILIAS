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

use ILIAS\Skill\Service\SkillService;
use ILIAS\Test\RequestDataCollector;
use ILIAS\Test\Logging\TestLogger;

/**
 * User interface which displays the competences which a learner has shown in a
 * test.
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilTestSkillGUI.php 46688 2013-12-09 15:23:17Z bheyser $
 *
 * @package		Modules/Test
 *
 * @ilCtrl_Calls ilTestSkillEvaluationGUI: ilTestSkillEvaluationToolbarGUI
 * @ilCtrl_Calls ilTestSkillEvaluationGUI: ilTestPersonalSkillsGUI
 */
class ilTestSkillEvaluationGUI
{
    public const INVOLVE_SKILLS_BELOW_NUM_ANSWERS_BARRIER_FOR_GAP_ANALASYS = false;

    public const SKILL_PROFILE_PARAM = 'skill_profile';
    public const CMD_SHOW = 'show';

    private ilTestSession $testSession;
    private ilTestObjectiveOrientedContainer $objectiveOrientedContainer;
    private ilAssQuestionList $questionList;

    protected bool $noSkillProfileOptionEnabled = false;
    protected array $availableSkillProfiles = [];
    protected array $available_skills = [];
    protected ?ilTestPassesSelector $testPassesSelector = null;

    public function __construct(
        private readonly ilObjTest $test_obj,
        private readonly ilCtrlInterface $ctrl,
        private readonly ilGlobalTemplateInterface $tpl,
        private readonly ilLanguage $lng,
        private readonly ilDBInterface $db,
        private readonly TestLogger $logger,
        private readonly SkillService $skills_service,
        private readonly RequestDataCollector $testrequest
    ) {
    }

    /**
     * @return ilAssQuestionList
     */
    public function getQuestionList(): ilAssQuestionList
    {
        return $this->questionList;
    }

    /**
     * @param ilAssQuestionList $questionList
     */
    public function setQuestionList($questionList)
    {
        $this->questionList = $questionList;
    }

    /**
     * @return ilTestObjectiveOrientedContainer
     */
    public function getObjectiveOrientedContainer(): ilTestObjectiveOrientedContainer
    {
        return $this->objectiveOrientedContainer;
    }

    /**
     * @param ilTestObjectiveOrientedContainer $objectiveOrientedContainer
     */
    public function setObjectiveOrientedContainer($objectiveOrientedContainer)
    {
        $this->objectiveOrientedContainer = $objectiveOrientedContainer;
    }

    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd(self::CMD_SHOW) . 'Cmd';
        $this->$cmd();
    }

    protected function init(bool $skill_profile_enabled): void
    {
        $this->testPassesSelector = new ilTestPassesSelector($this->db, $this->test_obj);
        $this->testPassesSelector->setActiveId($this->testSession->getActiveId());
        $this->testPassesSelector->setLastFinishedPass($this->testSession->getLastFinishedPass());

        $skillEvaluation = new ilTestSkillEvaluation(
            $this->db,
            $this->logger,
            $this->test_obj->getTestId(),
            $this->test_obj->getRefId(),
            $this->skills_service->profile(),
            $this->skills_service->personal()
        );

        $skillEvaluation->setUserId($this->getTestSession()->getUserId());
        $skillEvaluation->setActiveId($this->getTestSession()->getActiveId());

        $skillEvaluation->setNumRequiredBookingsForSkillTriggering(
            $this->test_obj->getGlobalSettings()->getSkillTriggeringNumberOfAnswers()
        );

        $skillEvaluation->init($this->getQuestionList());

        $availableSkillProfiles = $skillEvaluation->getAssignedSkillMatchingSkillProfiles();
        $this->setNoSkillProfileOptionEnabled(
            $skillEvaluation->noProfileMatchingAssignedSkillExists($availableSkillProfiles)
        );
        $this->setAvailableSkillProfiles($availableSkillProfiles);

        // should be reportedPasses - yes - indeed, skill level status will not respect - avoid confuse here
        $evaluationPasses = $this->testPassesSelector->getExistingPasses();

        $availableSkills = [];

        foreach ($evaluationPasses as $evalPass) {
            $testResults = $this->test_obj->getTestResult($this->getTestSession()->getActiveId(), $evalPass, true);

            $skillEvaluation->setPass($evalPass);
            $skillEvaluation->evaluate($testResults);

            if ($skill_profile_enabled && self::INVOLVE_SKILLS_BELOW_NUM_ANSWERS_BARRIER_FOR_GAP_ANALASYS) {
                $skills = $skillEvaluation->getSkillsInvolvedByAssignment();
            } else {
                $skills = $skillEvaluation->getSkillsMatchingNumAnswersBarrier();
            }

            $availableSkills = array_merge($availableSkills, $skills);
        }

        $this->setAvailableSkills(array_values($availableSkills));
    }

    private function showCmd()
    {
        $skill_profile_selected = $this->testrequest->isset(self::SKILL_PROFILE_PARAM);
        $selected_skill_profile = $this->testrequest->int(self::SKILL_PROFILE_PARAM);

        $this->init($skill_profile_selected);

        $personal_skills_gui = new ilPersonalSkillsGUI();
        $personal_skills_gui->setGapAnalysisActualStatusModePerObject(
            $this->test_obj->getId(),
            $this->lng->txt('tst_test_result')
        );
        $personal_skills_gui->setTriggerObjectsFilter([$this->test_obj->getId()]);
        $personal_skills_gui->setHistoryView(true);
        $personal_skills_gui->setProfileId($selected_skill_profile);

        $this->tpl->setContent(
            $this->buildEvaluationToolbarGUI($selected_skill_profile)->getHTML()
            . $personal_skills_gui->getGapAnalysisHTML(
                $this->getTestSession()->getUserId(),
                $this->available_skills
            )
        );
    }

    private function buildEvaluationToolbarGUI(int $selectedSkillProfileId): ilTestSkillEvaluationToolbarGUI
    {
        if (!$this->noSkillProfileOptionEnabled && $selectedSkillProfileId === null) {
            $selectedSkillProfileId = key($this->availableSkillProfiles) ?? 0;
        }

        $gui = new ilTestSkillEvaluationToolbarGUI($this->ctrl, $this->lng);
        $gui->setAvailableSkillProfiles($this->availableSkillProfiles);
        $gui->setNoSkillProfileOptionEnabled($this->noSkillProfileOptionEnabled);
        $gui->setSelectedEvaluationMode($selectedSkillProfileId);
        $gui->build();
        return $gui;
    }

    public function setTestSession(ilTestSession $testSession): void
    {
        $this->testSession = $testSession;
    }

    public function getTestSession(): ilTestSession
    {
        return $this->testSession;
    }

    public function setNoSkillProfileOptionEnabled(bool $noSkillProfileOptionEnabled): void
    {
        $this->noSkillProfileOptionEnabled = $noSkillProfileOptionEnabled;
    }

    public function setAvailableSkillProfiles(array $availableSkillProfiles): void
    {
        $this->availableSkillProfiles = $availableSkillProfiles;
    }

    public function setAvailableSkills(array $availableSkills): void
    {
        $this->available_skills = $availableSkills;
    }
}
