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
 ********************************************************************
 */

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\UI\Component\Input\Factory as InputFactory;
use ILIAS\UI\Component\Input\Container\Form\Form;

/**
 * Global orgunit settings GUI
 * @author            Stefan Meyer <smeyer.ilias@gmx.de>
 * @ilCtrl_IsCalledBy ilOrgUnitGlobalSettingsGUI: ilObjOrgUnitGUI
 */
class ilOrgUnitGlobalSettingsGUI
{
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilGlobalTemplateInterface $tpl;

    protected ILIAS\UI\Renderer $renderer;
    protected ILIAS\UI\Factory $factory;
    protected InputFactory $input_factory;
    protected ILIAS\Refinery\Factory $refinery;
    protected Psr\Http\Message\ServerRequestInterface $request;

    protected $obj_definition;
    protected ilSetting $settings;

    public function __construct()
    {
        global $DIC;
        $main_tpl = $DIC->ui()->mainTemplate();

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->lng->loadLanguageModule('orgu');
        $this->tpl = $DIC->ui()->mainTemplate();

        $this->obj_definition = $DIC['objDefinition'];
        $this->settings = $DIC->settings();

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->input_factory = $DIC->ui()->factory()->input();
        $this->refinery = $DIC->refinery();

        $this->request = $DIC->http()->request();

        if (!ilObjOrgUnitAccess::_checkAccessSettings((int) $_GET['ref_id'])) {
            $main_tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->redirectByClass(ilObjOrgUnitGUI::class);
        }
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd('view');
        $next_class = $this->ctrl->getNextClass($this);

        switch ($cmd) {
            case 'settings':
                $content = $this->settings();
                $this->tpl->setContent($content);
                break;
            case 'view':
                $this->view();
                break;
            case 'saveSettings':
                $this->saveSettings();
                break;
            case 'update':
                $this->update();
                break;
            default:
                throw new ilException(
                    "ilOrgUnitGlobalSettingsGUI: Command not supported: $cmd"
                );
        }
    }

    private function view(): void
    {
        $form = $this->buildForm($this->ctrl->getFormAction($this, "update"));
        $this->tpl->setContent($this->renderer->render($form));
    }

    private function update(): void
    {
        $form = $this->buildForm($this->ctrl->getFormAction($this, "update"))
                     ->withRequest($this->request);

        $result = $form->getInputGroup()->getContent();
        if ($result->isOK()) {
            if ($this->saveForm($form)) {
                $this->tpl->setOnScreenMessage("success", $this->lng->txt('settings_saved'), true);
                $this->ctrl->redirect($this, 'view');
            }
        }

        $this->tpl->setOnScreenMessage("failure", $this->lng->txt('err_check_input'));
        $this->tpl->setContent($this->renderer->render($form));
    }

    private function saveForm(Form $form): bool
    {
        $data = $form->getData();

        $available_types = $this->obj_definition->getOrgUnitPermissionTypes();
        foreach ($available_types as $object_type) {
            $is_active = false;
            if ($data['positions'][$object_type . '_active'] === true
                || is_array($data['positions'][$object_type . '_active'])) {
                $is_active = true;
            }
            $is_changeable = $data['positions'][$object_type . '_active'][$object_type . '_changeable'] ?? false;
            $set_default = $data['positions'][$object_type . '_active'][$object_type . '_default'] ?? 0;

            $obj_setting = new ilOrgUnitObjectTypePositionSetting($object_type);
            $obj_setting->setActive($is_active);
            $obj_setting->setChangeableForObject($is_changeable);
            $obj_setting->setActivationDefault($set_default);
            $obj_setting->update();
        }

        $this->settings->set("enable_my_staff", $data['staff']['enable_my_staff']);

        return true;
    }

    private function buildForm(string $submit_action): Form
    {
        $sections = $this->buildFormElements();
        $form = $this->input_factory->container()->form()->standard(
            $submit_action,
            $sections
        )->withDedicatedName('mainform');
        return $form;
    }

    private function buildFormElements(): array
    {
        global $DIC;
        $sections = [];

        $staff = $this->input_factory->field()->checkbox(
            $this->lng->txt('orgu_enable_my_staff'),
            $this->lng->txt("orgu_enable_my_staff_info")
        )
                                     ->withValue($this->settings->get("enable_my_staff") === '1' ? true : false);
        //->withDedicatedName('enable_staff');

        $staff = $staff->withAdditionalTransformation($this->refinery->kindlyTo()->int());

//        $text = $this->input_factory->field()->text('My text', null, 'iamtext');
//        $numeric = $this->input_factory->field()->numeric('My numeric', null, 'iamnumeric');
//        $tag = $this->input_factory->field()->tag('My tag', [], null, 'iamtag');
//        $password = $this->input_factory->field()->password('My password', null, 'iampw');
//        $select = $this->input_factory->field()->select('My select', ['one', 'two'], null, 'iamselect');
//        $textarea = $this->input_factory->field()->textarea('My textarea', null, 'iamarea');
//        $multiselect = $this->input_factory->field()->multiselect('My multiselect', ['one', 'two'], null, 'iammultiselect');
//        $datetime = $this->input_factory->field()->datetime('My datetime', null, 'iamdatetime');
//        $duration = $this->input_factory->field()->duration('My duration', null, 'iamduration');
//        $file_input = $this->input_factory->field()->file(new \ilUIDemoFileUploadHandlerGUI(), "Upload File", "you can drop your files here", null, null)->withMaxFiles(3);
//        $url = $this->input_factory->field()->url('My url', null, 'iamurl');
//        $link = $this->input_factory->field()->link('My link', null, 'iamlink');
//        $hidden = $this->input_factory->field()->hidden('iamhidden');
//        $picker = $this->input_factory->field()->colorpicker('My color', null, 'iampicker');

        $sections['staff'] = $this->input_factory->field()->section(
            [
                'enable_my_staff' => $staff,
                //                'my_text' => $text,
                //                'my_numeric' => $numeric,
                //                'my_tag' => $tag,
                //                'my_pw' => $password,
                //                'my_select' => $select,
                //                'my_multiselect' => $multiselect,
                //                'my_datetime' => $datetime,
                //                'my_duration' => $duration,
                //                'my_textarea' => $textarea,
                //                'my_file' => $file_input,
                //                'my_url' => $url,
                //                'my_link' => $link,
                //                'my_picker' => $picker,
                //                'my_hidden' => $hidden
            ],
            $this->lng->txt('orgu_enable_my_staff'),
            null
        )->withDedicatedName('staff');

        $available_types = $this->obj_definition->getOrgUnitPermissionTypes();
        $checkboxes = [];
        foreach ($available_types as $object_type) {
            $setting = new ilOrgUnitObjectTypePositionSetting($object_type);
            $is_multi = false;

            if ($this->obj_definition->isPlugin($object_type)) {
                $label = ilObjectPlugin::lookupTxtById($object_type, 'objs_' . $object_type);
            } else {
                $is_multi = (! $this->obj_definition->isSystemObject($object_type)
                    && $object_type != ilOrgUnitOperationContext::CONTEXT_ETAL);
                $lang_prefix = $is_multi ? 'objs_' : 'obj_';
                $label = $this->lng->txt($lang_prefix . $object_type);
            }

            if ($is_multi) {
                // Setting changeable
                $changeable = $this->input_factory->field()->radio($this->lng->txt('orgu_global_set_type_changeable'), null)
                                                  ->withDedicatedName('changeable')
                                                  ->withOption('0', $this->lng->txt('orgu_global_set_type_changeable_no'))
                                                  ->withOption('1', $this->lng->txt('orgu_global_set_type_changeable_object'))
                                                  ->withRequired(true);

                $is_required = $this->refinery->custom()->constraint(
                    function ($v) {
                        return in_array($v, ['0', '1']);
                    },
                    $this->lng->txt('fill_out_all_required_fields')
                );
                $changeable = $changeable
                    ->withAdditionalTransformation($is_required)
                    ->withAdditionalTransformation($this->refinery->kindlyTo()->bool());

                // Define default setting
                $default = $this->input_factory->field()->checkbox(
                    $this->lng->txt('orgu_global_set_type_default'),
                    $this->lng->txt('orgu_global_set_type_default_info')
                )->withDedicatedName('default');

                $default = $default
                    ->withAdditionalTransformation($this->refinery->kindlyTo()->int());

                // Position active
                $check = $this->input_factory->field()->optionalGroup(
                    [
                        $object_type . '_changeable' => $changeable,
                        $object_type . '_default' => $default
                    ],
                    $this->lng->txt('orgu_global_set_positions_type_active') . ' ' . $label,
                    null
                ); //->withDedicatedName($object_type . '_active');

                if ($setting->isActive()) {
                    $check = $check->withValue([
                        $object_type . '_changeable' => $setting->isChangeableForObject() ? '1' : '0',
                        $object_type . '_default' => (bool) $setting->getActivationDefault()
                    ]);
                } else {
                    $check = $check->withValue(null);
                }
            } else {
                $check = $this->input_factory->field()->checkbox(
                    $this->lng->txt('orgu_global_set_positions_type_active') . ' ' . $label,
                    null
                )
                                             ->withDedicatedName('activate_' . $object_type)
                                             ->withValue($setting->isActive());
            }

            $checkboxes[$object_type . '_active'] = $check;
        }

        $sections['positions'] = $this->input_factory->field()->section(
            $checkboxes,
            $this->lng->txt('orgu_global_set_positions'),
            null
        ); //->withDedicatedName('positions');

        return $sections;
    }

//    private function settings(ilPropertyFormGUI $form = null): string
//    {
//        if (!$form instanceof ilPropertyFormGUI) {
//            $form = $this->initSettingsForm();
//        }
//        return $form->getHTML();
//    }
//
//    private function initSettingsForm(): ilPropertyFormGUI
//    {
//        global $DIC;
//
//        $form = new ilPropertyFormGUI();
//        $form->setFormAction($this->ctrl->getFormAction($this, 'saveSettings'));
//
//        // My Staff
//        $section = new ilFormSectionHeaderGUI();
//        $section->setTitle($this->lng->txt('orgu_enable_my_staff'));
//        $form->addItem($section);
//
//        $item = new ilCheckboxInputGUI($this->lng->txt("orgu_enable_my_staff"), "enable_my_staff");
//        $item->setInfo($this->lng->txt("orgu_enable_my_staff_info"));
//        $item->setValue("1");
//        $item->setChecked(($DIC->settings()->get("enable_my_staff") ? true : false));
//        $form->addItem($item);
//
//        // Positions in Modules
//        $section = new ilFormSectionHeaderGUI();
//        $section->setTitle($this->lng->txt('orgu_global_set_positions'));
//        $form->addItem($section);
//
//        $objDefinition = $DIC['objDefinition'];
//        $available_types = $objDefinition->getOrgUnitPermissionTypes();
//        foreach ($available_types as $object_type) {
//            $setting = new ilOrgUnitObjectTypePositionSetting($object_type);
//            $is_multi = false;
//
//            if ($objDefinition->isPlugin($object_type)) {
//                $label = ilObjectPlugin::lookupTxtById($object_type, 'objs_' . $object_type);
//            } else {
//                $is_multi = !$objDefinition->isSystemObject($object_type) && $object_type != ilOrgUnitOperationContext::CONTEXT_ETAL;
//                $lang_prefix = $is_multi ? 'objs_' : 'obj_';
//                $label = $this->lng->txt($lang_prefix . $object_type);
//            }
//
//            $type = new ilCheckboxInputGUI(
//                $this->lng->txt('orgu_global_set_positions_type_active') . ' ' . $label,
//                $object_type . '_active'
//            );
//            $type->setValue(1);
//            $type->setChecked($setting->isActive());
//            if ($is_multi) {
//                $scope = new ilRadioGroupInputGUI(
//                    $this->lng->txt('orgu_global_set_type_changeable'),
//                    $object_type . '_changeable'
//                );
//                $scope->setValue((int) $setting->isChangeableForObject());
//
//                $scope_object = new ilRadioOption(
//                    $this->lng->txt('orgu_global_set_type_changeable_object'),
//                    1
//                );
//                $default = new ilCheckboxInputGUI(
//                    $this->lng->txt('orgu_global_set_type_default'),
//                    $object_type . '_default'
//                );
//                $default->setInfo($this->lng->txt('orgu_global_set_type_default_info'));
//                $default->setValue(ilOrgUnitObjectTypePositionSetting::DEFAULT_ON);
//                $default->setChecked($setting->getActivationDefault());
//
//                $scope_object->addSubItem($default);
//                $scope->addOption($scope_object);
//
//                $scope_global = new ilRadioOption(
//                    $this->lng->txt('orgu_global_set_type_changeable_no'),
//                    0
//                );
//                $scope->addOption($scope_global);
//
//                $type->addSubItem($scope);
//            }
//            $form->addItem($type);
//        }
//        $form->addCommandButton('saveSettings', $this->lng->txt('save'));
//
//        return $form;
//    }
//
//    private function saveSettings(): void
//    {
//        global $DIC;
//        $objDefinition = $DIC['objDefinition'];
//        $form = $this->initSettingsForm();
//        if ($form->checkInput()) {
//            // Orgu Permissions / Positions in Modules
//            $available_types = $objDefinition->getOrgUnitPermissionTypes();
//            foreach ($available_types as $object_type) {
//                $obj_setting = new ilOrgUnitObjectTypePositionSetting($object_type);
//                $obj_setting->setActive((bool) $form->getInput($object_type . '_active'));
//                $obj_setting->setActivationDefault((int) $form->getInput($object_type . '_default'));
//                $obj_setting->setChangeableForObject((bool) $form->getInput($object_type
//                    . '_changeable'));
//                $obj_setting->update();
//            }
//
//            // MyStaff
//            $DIC->settings()->set("enable_my_staff", (int) $form->getInput('enable_my_staff'));
//
//            $this->tpl->setOnScreenMessage('success', $this->lng->txt('settings_saved'), true);
//            $this->ctrl->redirect($this, 'settings');
//        } else {
//            $form->setValuesByPost();
//            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'), false);
//            $this->settings($form);
//        }
//    }
}
