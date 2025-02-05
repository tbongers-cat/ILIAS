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

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\FileUpload;
use ILIAS\FileUpload\Handler\BasicHandlerResult;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\Handler\HandlerResult;

/**
 * Class ilPCSourcecodeGUI
 *
 * User Interface for Paragraph Editing
 * @ilCtrl_Calls ilPCSourceCodeGUI: ilRepoStandardUploadHandlerGUI
 * @author Alexander Killing <killing@leifos.de>
 */
class ilPCSourceCodeGUI extends ilPageContentGUI
{
    protected ilTabsGUI $tabs;
    /**
     * @var mixed
     */
    protected string $requested_par_downloadtitle;
    protected string $requested_par_content;
    protected ilObjUser $user;

    public function __construct(
        ilPageObject $a_pg_obj,
        ?ilPageContent $a_content_obj,
        string $a_hier_id,
        string $a_pc_id = ""
    ) {
        global $DIC;

        $this->user = $DIC->user();
        parent::__construct($a_pg_obj, $a_content_obj, $a_hier_id, $a_pc_id);
        $this->tabs = $DIC->tabs();
    }

    public function executeCommand(): void
    {
        // get next class that processes or forwards current command
        $next_class = $this->ctrl->getNextClass($this);

        // get current command
        $cmd = $this->ctrl->getCmd();

        switch ($next_class) {

            case strtolower(ilRepoStandardUploadHandlerGUI::class):
                $form = $this->getImportFormAdapter();
                $gui = $form->getRepoStandardUploadHandlerGUI("input_file");
                $this->ctrl->forwardCommand($gui);
                break;

            default:
                $this->$cmd();
                break;
        }
    }

    public function edit(): void
    {
        $form = $this->initPropertyForm($this->lng->txt("cont_edit_src"), "update", "cancelCreate");

        $this->displayValidationError();

        $this->initEditor();
        $this->tabs->setBackTarget("", "");

        $cmd = $this->ctrl->getCmd();
        if ($cmd == "update") {
            $form->setValuesByPost();
        } else {
            /*
            $form->getItemByPostVar("par_language")->setValue($this->content_obj->getLanguage());
            $form->getItemByPostVar("par_subcharacteristic")->setValue($this->content_obj->getSubCharacteristic());
            $form->getItemByPostVar("par_downloadtitle")->setValue($this->content_obj->getDownloadTitle());
            $form->getItemByPostVar("par_showlinenumbers")->setChecked(
                $this->content_obj->getShowLineNumbers() == "y"
            );
            //			$form->getItemByPostVar("par_autoindent")->setChecked(
            //				$this->content_obj->getAutoIndent()=="y"?true:false);
            */
            $par_content = $this->content_obj->xml2output($this->content_obj->getText());

            $par_content = str_replace("&#123;", "[curlybegin ", $par_content);
            $par_content = str_replace("&#125;", " curlyend]", $par_content);

            $form->getItemByPostVar("par_content")->setValue($par_content);
        }

        $f = $this->gui
            ->ui()
            ->factory()->input()->field()
                                ->textarea(
                                    $this->lng->txt("cont_pc_code")
                                )->withValue($par_content);
        $t = $this->gui->ui()->renderer()->render($f);
        $t = str_replace("<textarea", "<textarea name='code' rows='20' form='copg-src-form' ", $t);
        $t = str_replace("[curlybegin ", "&#123;", $t);
        $t = str_replace(" curlyend]", "&#125;", $t);
        $this->tpl->setContent($t . $this->getEditorScriptTag($this->pc_id, "SourceCode"));
    }

    public function insert(): void
    {
        $ilUser = $this->user;

        $form = $this->initPropertyForm($this->lng->txt("cont_insert_src"), "create_src", "cancelCreate");

        if ($this->pg_obj->getParentType() == "lm") {
            $this->tpl->setVariable(
                "LINK_ILINK",
                $this->ctrl->getLinkTargetByClass("ilInternalLinkGUI", "showLinkHelp")
            );
            $this->tpl->setVariable("TXT_ILINK", "[" . $this->lng->txt("cont_internal_link") . "]");
        }

        $this->displayValidationError();

        $cmd = $this->ctrl->getCmd();
        if ($cmd == "create_src") {
            $form->setValuesByPost();
        } else {
            if ($this->getCurrentTextLang() != "") {
                $form->getItemByPostVar("par_language")->setValue($this->getCurrentTextLang());
            } else {
                $form->getItemByPostVar("par_language")->setValue($ilUser->getLanguage());
            }

            $form->getItemByPostVar("par_showlinenumbers")->setChecked(true);
            //			$form->getItemByPostVar("par_autoindent")->setChecked(true);
            $form->getItemByPostVar("par_subcharacteristic")->setValue("");
            $form->getItemByPostVar("par_content")->setValue("");
        }

        $this->tpl->setContent($form->getHTML());
    }

    public function update(): void
    {
        $this->requested_par_content = $this->request->getRaw("par_content");
        $this->requested_par_downloadtitle = str_replace('"', '', $this->request->getString("par_downloadtitle"));

        //        $this->upload_source();

        // set language and characteristic

        /*
        $this->content_obj->setLanguage(
            $this->request->getString("par_language")
        );
        $this->content_obj->setCharacteristic($this->request->getString("par_characteristic"));*/

        // set language and characteristic
        /*$this->content_obj->setLanguage($this->request->getString("par_language"));
        $this->content_obj->setSubCharacteristic($this->request->getString("par_subcharacteristic"));
        $this->content_obj->setDownloadTitle(
            str_replace('"', '', $this->requested_par_downloadtitle)
        );
        $this->content_obj->setShowLineNumbers(
            $this->request->getString("par_showlinenumbers") ? "y" : "n"
        );
        $this->content_obj->setSubCharacteristic($this->request->getString("par_subcharacteristic"));
        $this->content_obj->setCharacteristic("Code");

        */

        $this->updated = $this->content_obj->setText(
            $this->content_obj->input2xml($this->requested_par_content, 0, false)
        );

        if ($this->updated !== true) {
            //echo "Did not update!";
            $this->edit();
            return;
        }

        $this->updated = $this->pg_obj->update();

        if ($this->updated === true && $this->ctrl->getCmd() != "upload") {
            $this->ctrl->returnToParent($this, "jump" . $this->hier_id);
        } else {
            $this->edit();
        }
    }

    public function cancelUpdate(): void
    {
        $this->ctrl->returnToParent($this, "jump" . $this->hier_id);
    }

    public function create(): void
    {
        $this->content_obj = new ilPCSourceCode($this->getPage());
        $this->content_obj->create($this->pg_obj, $this->hier_id, $this->pc_id);
        $this->content_obj->setLanguage($this->request->getString("par_language"));

        $this->setCurrentTextLang($this->request->getString("par_language"));

        $this->requested_par_content = $this->request->getRaw("par_content");
        $this->requested_par_downloadtitle = str_replace('"', '', $this->request->getString("par_downloadtitle"));

        $uploaded = $this->upload_source();

        $this->content_obj->setCharacteristic(
            $this->request->getString("par_characteristic")
        );
        $this->content_obj->setSubCharacteristic(
            $this->request->getString("par_subcharacteristic")
        );
        $this->content_obj->setDownloadTitle(str_replace('"', '', $this->requested_par_downloadtitle));
        $this->content_obj->setShowLineNumbers(
            $this->request->getString("par_showlinenumbers") ? 'y' : 'n'
        );
        $this->content_obj->setCharacteristic('Code');

        if ($uploaded) {
            $this->insert();
            return;
        }

        $this->updated = $this->content_obj->setText(
            $this->content_obj->input2xml($this->requested_par_content, 0, false)
        );

        if ($this->updated !== true) {
            $this->insert();
            return;
        }

        $this->updated = $this->pg_obj->update();

        if ($this->updated === true) {
            $this->ctrl->returnToParent($this, "jump" . $this->hier_id);
        } else {
            $this->insert();
        }
    }

    public function cancelCreate(): void
    {
        $this->ctrl->returnToParent($this, "jump" . $this->hier_id);
    }

    public function upload_source(): bool
    {
        if (isset($_FILES['userfile']['name'])) {
            $userfile = $_FILES['userfile']['tmp_name'];

            if ($userfile == "" || !is_uploaded_file($userfile)) {
                $error_str = "<strong>Error(s):</strong><br>Upload error: file name must not be empty!";
                $this->tpl->setVariable("MESSAGE", $error_str);
                $this->content_obj->setText(
                    $this->content_obj->input2xml(
                        $this->request->getRaw("par_content"),
                        0,
                        false
                    )
                );
                return false;
            }

            $this->requested_par_content = file_get_contents($userfile);
            $this->requested_par_downloadtitle = $_FILES['userfile']['name'];
            return true;
        }

        return false;
    }


    /**
     * Get selectable programming languages
     * @return string[]
     */
    public function getProgLangOptions(): array
    {
        $prog_langs = array(
            "" => "other");
        foreach (ilSyntaxHighlighter::getSupportedLanguagesV51() as $k => $v) {
            $prog_langs[$k] = $v;
        }
        return $prog_langs;
    }

    public function initPropertyForm(
        string $a_title,
        string $a_cmd,
        string $a_cmd_cancel
    ): ilPropertyFormGUI {
        $form = new ilPropertyFormGUI();
        $form->setTitle($a_title);
        $form->setFormAction($this->ctrl->getFormAction($this, $a_cmd));
        $form->addCommandButton($a_cmd, $this->lng->txt("save"));
        $form->addCommandButton($a_cmd_cancel, $this->lng->txt("cancel"));

        /*
        $lang_var = ilMDLanguageItem::_getLanguages();
        $lang = new ilSelectInputGUI($this->lng->txt("language"), "par_language");
        $lang->setOptions($lang_var);
        $form->addItem($lang);

        $prog_langs = $this->getProgLangOptions();
        $code_style = new ilSelectInputGUI($this->lng->txt("cont_src"), "par_subcharacteristic");
        $code_style->setOptions($prog_langs);
        $form->addItem($code_style);
        $line_number = new ilCheckboxInputGUI($this->lng->txt("cont_show_line_numbers"), "par_showlinenumbers");
        $form->addItem($line_number);
*/
        $code = new ilTextAreaInputGUI("", "par_content");
        $code->setRows(12);
        $form->addItem($code);

        /*
        $downlaod_title = new ilTextInputGUI($this->lng->txt("cont_download_title"), "par_downloadtitle");
        $downlaod_title->setSize(40);
        $form->addItem($downlaod_title);

        $file = new ilFileInputGUI($this->lng->txt("import_file"), "userfile");
        $form->addItem($file);*/

        return $form;
    }

    public function getImportFormAdapter(): \ILIAS\Repository\Form\FormAdapterGUI
    {
        $this->ctrl->setParameter($this, "cname", "SourceCode");
        $form = $this->gui->form([self::class], "#")
                          ->async()
                          ->hidden("mode", "import")
                          ->file(
                              "input_file",
                              $this->lng->txt("import_file"),
                              \Closure::fromCallable([$this, 'handleUploadResult']),
                              "filename",
                              "",
                              1,
                              [],
                              [self::class],
                              "copg"
                          )
                          ->text(
                              "title",
                              $this->lng->txt("cont_download_title")
                          )
            ->select(
                "subchar",
                $this->lng->txt("cont_src"),
                $this->getProgLangOptions()
            )
            ->checkbox(
                "linenumbers",
                $this->lng->txt("cont_show_line_numbers")
            );
        return $form;
    }

    public function getManualFormAdapter(
        ?string $download_title = null,
        ?string $subchar = null,
        ?bool $line_numbers = null
    ): \ILIAS\Repository\Form\FormAdapterGUI {
        $this->ctrl->setParameter($this, "cname", "SourceCode");
        $form = $this->gui->form([self::class], "#")
                          ->async()
                          ->hidden("mode", "manual")
                          ->text(
                              "title",
                              $this->lng->txt("cont_download_title"),
                              "",
                              $download_title
                          )
                          ->select(
                              "subchar",
                              $this->lng->txt("cont_src"),
                              $this->getProgLangOptions(),
                              "",
                              $subchar
                          )
                          ->checkbox(
                              "linenumbers",
                              $this->lng->txt("cont_show_line_numbers"),
                              "",
                              $line_numbers
                          );
        return $form;
    }

    public function getEditingFormAdapter(): \ILIAS\Repository\Form\FormAdapterGUI
    {
        return $this->getManualFormAdapter(
            $this->content_obj->getDownloadTitle(),
            $this->content_obj->getSubCharacteristic(),
            ($this->content_obj->getShowLineNumbers() == "y")
        );
    }

    public function handleUploadResult(
        FileUpload $upload,
        UploadResult $result
    ): BasicHandlerResult {
        $fac = new ILIAS\Data\UUID\Factory();
        $uuid = $fac->uuid4AsString();
        $name = $uuid . ".txt";
        $upload->moveOneFileTo(
            $result,
            "",
            Location::TEMPORARY,
            $name,
            true
        );

        return new BasicHandlerResult(
            "filename",
            HandlerResult::STATUS_OK,
            $name,
            ''
        );
    }
}
