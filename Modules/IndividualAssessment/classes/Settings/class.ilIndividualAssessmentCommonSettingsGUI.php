<?php

declare(strict_types=1);

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

class ilIndividualAssessmentCommonSettingsGUI
{
    public const CMD_EDIT = 'editSettings';
    public const CMD_SAVE = 'saveSettings';

    public function __construct(
        protected ilObjIndividualAssessment $object,
        protected ilCtrl $ctrl,
        protected ilGlobalTemplateInterface $tpl,
        protected ilLanguage $lng,
        protected ilObjectService $object_service,
        protected ILIAS\Refinery\Factory $refinery,
        protected ILIAS\UI\Factory $ui_factory,
        protected ILIAS\UI\Renderer $renderer,
        protected Psr\Http\Message\ServerRequestInterface $request
    ) {
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            case self::CMD_EDIT:
                $content = $this->editSettings();
                break;
            case self::CMD_SAVE:
                $content = $this->saveSettings();
                break;
            default:
                throw new Exception('Unknown command ' . $cmd);
        }

        $this->tpl->setContent($content);
    }

    protected function editSettings(): string
    {
//        if (is_null($form)) {
//            $form = $this->buildForm();
//        }

        return $this->buildForm()->getHTML();

//        return $this->renderer->render($this->buildForm2(
//            $this->object,
//            $this->ctrl->getFormAction($this, self::CMD_SAVE)
//        ));
    }

    protected function buildForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->txt('obj_features'));
        $form->addCommandButton(self::CMD_SAVE, $this->txt('save'));
        $form->addCommandButton(self::CMD_EDIT, $this->txt('cancel'));

        $this->addServiceSettingsToForm($form);
        $this->addCommonFieldsToForm($form);

        return $form;
    }

    protected function buildForm2(
        ilObjIndividualAssessment $iass,
        string $submit_action
    ): ILIAS\UI\Component\Input\Container\Form\Standard {
        $if = $this->ui_factory->input();

        $form = $if->container()->form()->standard(
            $submit_action,
            $this->buildFormElements(
                $iass,
                $if
            )
        )->withAdditionalTransformation(
            $this->refinery->custom()->transformation(
                function ($values) {
                }
            )
        );

        return $form;
    }

    protected function buildFormElements(
        ilObjIndividualAssessment $iass,
        ILIAS\UI\Component\Input\Factory $if
    ) {
        $txt = fn($id) => $this->lng->txt($id);
        $formElements = [];

        $image = $iass->getObjectProperties()->getPropertyTileImage()->toForm(
            $this->lng,
            $if->field(),
            $this->refinery
        );
        $section_common = $if->field()->section(
            [
                'image' => $image
            ],
            $txt('cont_presentation')
        );
        $formElements['common'] = $section_common;

        return $formElements;
    }

    protected function addServiceSettingsToForm(ilPropertyFormGUI $form): void
    {
        ilObjectServiceSettingsGUI::initServiceSettingsForm(
            $this->object->getId(),
            $form,
            [
                ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS,
                ilObjectServiceSettingsGUI::CUSTOM_METADATA
            ]
        );
    }

    protected function addCommonFieldsToForm(ilPropertyFormGUI $form): void
    {
        $section_appearance = new ilFormSectionHeaderGUI();
        $section_appearance->setTitle($this->txt('cont_presentation'));
        $form->addItem($section_appearance);
        $form_service = $this->object_service->commonSettings()->legacyForm($form, $this->object);
        $form_service->addTileImage();
    }

    protected function saveSettings(): ?string
    {
        $form = $this
            ->buildForm($this->ctrl->getFormAction($this, self::CMD_SAVE))
            ->withRequest($this->request);

        $result = $form->getInputGroup()->getContent();

        if ($result->isOK()) {
//            $result->value()->update();

            $this->tpl->setOnScreenMessage("success", $this->lng->txt('iass_settings_saved'), true);
            $this->ctrl->redirect($this, self::CMD_EDIT);
            return null;
        } else {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt("msg_form_save_error"));
            return $this->renderer->render($form);
        }


//        $form = $this->buildForm();
//
//        if (!$form->checkInput()) {
//            $form->setValuesByPost();
//            $this->editSettings($form);
//            return;
//        }
//
//        ilObjectServiceSettingsGUI::updateServiceSettingsForm(
//            $this->object->getId(),
//            $form,
//            [
//                ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS,
//                ilObjectServiceSettingsGUI::CUSTOM_METADATA
//            ]
//        );
//
//        $form_service = $this->object_service->commonSettings()->legacyForm($form, $this->object);
//        $form_service->saveTileImage();

        $this->tpl->setOnScreenMessage("success", $this->lng->txt('iass_settings_saved'), true);
        $this->ctrl->redirect($this, self::CMD_EDIT);
    }

    protected function txt(string $code): string
    {
        return $this->lng->txt($code);
    }
}
