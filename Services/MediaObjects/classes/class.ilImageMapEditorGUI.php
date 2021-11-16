<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */

/**
 * User interface class for map editor
 * @author Alexander Killing <killing@leifos.de>
 * @ilCtrl_Calls ilImageMapEditorGUI: ilInternalLinkGUI
 */
class ilImageMapEditorGUI
{
    protected ilTemplate $tpl;
    protected ilGlobalTemplateInterface $main_tpl;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilToolbarGUI $toolbar;

    public function __construct(
        ilObjMediaObject $a_media_object
    ) {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->main_tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->toolbar = $DIC->toolbar();
        $this->media_object = $a_media_object;
    }

    /**
     * @return mixed
     * @throws ilCtrlException
     */
    public function executeCommand()
    {
        $ilCtrl = $this->ctrl;

        $next_class = $ilCtrl->getNextClass($this);
        $cmd = $ilCtrl->getCmd();

        switch ($next_class) {
            case "ilinternallinkgui":
                $link_gui = new ilInternalLinkGUI("Media_Media", 0);
                $link_gui->setSetLinkTargetScript(
                    $ilCtrl->getLinkTarget(
                        $this,
                        "setInternalLink"
                    )
                );
                $link_gui->filterLinkType("File");
                $ret = $ilCtrl->forwardCommand($link_gui);
                break;

            default:
                ilObjMediaObjectGUI::includePresentationJS();
                if (isset($_POST["editImagemapForward"]) ||
                    isset($_POST["editImagemapForward_x"]) ||
                    isset($_POST["editImagemapForward_y"])) {
                    $cmd = "editImagemapForward";
                }
                $ret = $this->$cmd();
                break;
        }
        return $ret;
    }
        
    public function editMapAreas() : string
    {
        $ilCtrl = $this->ctrl;

        $_SESSION["il_map_edit_target_script"] = $ilCtrl->getLinkTarget(
            $this,
            "addArea",
            "",
            false,
            false
        );
        $this->handleMapParameters();

        $this->tpl = new ilTemplate("tpl.map_edit.html", true, true, "Services/MediaObjects");
        $this->tpl->setVariable("FORMACTION", $ilCtrl->getFormAction($this));

        // create/update imagemap work copy
        $this->makeMapWorkCopy();

        $output = $this->getImageMapOutput();
        $this->tpl->setVariable("IMAGE_MAP", $output);
        
        $this->tpl->setVariable("TOOLBAR", $this->getToolbar()->getHTML());
        
        // table
        $this->tpl->setVariable("MAP_AREA_TABLE", $this->getImageMapTableHTML());
        
        return $this->tpl->get();
    }

    public function getToolbar() : ilToolbarGUI
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        // toolbar
        $tb = new ilToolbarGUI();
        $tb->setFormAction($ilCtrl->getFormAction($this));
        $options = array(
            "WholePicture" => $lng->txt("cont_WholePicture"),
            "Rect" => $lng->txt("cont_Rect"),
            "Circle" => $lng->txt("cont_Circle"),
            "Poly" => $lng->txt("cont_Poly"),
            );
        $si = new ilSelectInputGUI($lng->txt("cont_shape"), "shape");
        $si->setOptions($options);
        $tb->addInputItem($si, true);
        $tb->addFormButton($lng->txt("cont_add_area"), "addNewArea");
        
        return $tb;
    }
    
    public function getEditorTitle() : string
    {
        $lng = $this->lng;
        return $lng->txt("cont_imagemap");
    }
    
    
    public function getImageMapTableHTML() : string
    {
        $image_map_table = new ilImageMapTableGUI($this, "editMapAreas", $this->media_object);
        return $image_map_table->getHTML();
    }
    
    public function handleMapParameters() : void
    {
        if ($_GET["ref_id"] != "") {
            $_SESSION["il_map_edit_ref_id"] = $_GET["ref_id"];
        }

        if ($_GET["obj_id"] != "") {
            $_SESSION["il_map_edit_obj_id"] = $_GET["obj_id"];
        }

        if ($_GET["hier_id"] != "") {
            $_SESSION["il_map_edit_hier_id"] = $_GET["hier_id"];
        }
        
        if ($_GET["pc_id"] != "") {
            $_SESSION["il_map_edit_pc_id"] = $_GET["pc_id"];
        }
    }

    public function showImageMap() : void
    {
        $item = new ilMediaItem($_GET["item_id"]);
        $item->outputMapWorkCopy();
    }

    public function updateAreas() : void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        $st_item = $this->media_object->getMediaItem("Standard");
        $max = ilMapArea::_getMaxNr($st_item->getId());
        for ($i = 1; $i <= $max; $i++) {
            $area = new ilMapArea($st_item->getId(), $i);
            $area->setTitle(ilUtil::stripSlashes($_POST["name_" . $i]));
            $area->setHighlightMode(ilUtil::stripSlashes($_POST["hl_mode_" . $i]));
            $area->setHighlightClass(ilUtil::stripSlashes($_POST["hl_class_" . $i]));
            $area->update();
        }

        ilUtil::sendSuccess($lng->txt("cont_saved_map_data"), true);
        $ilCtrl->redirect($this, "editMapAreas");
    }

    public function addNewArea() : string
    {
        switch ($_POST["shape"]) {
            case "WholePicture": return $this->linkWholePicture();
            case "Rect": return $this->addRectangle();
            case "Circle": return $this->addCircle();
            case "Poly": return $this->addPolygon();
        }
        return "";
    }
    
    public function linkWholePicture() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "WholePicture";

        return $this->editMapArea(false, false, true);
    }

    public function addRectangle() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Rect";
        return $this->addArea(false);
    }

    public function addCircle() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Circle";
        return $this->addArea(false);
    }

    public function addPolygon() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Poly";
        return $this->addArea(false);
    }

    public function clearSessionVars() : void
    {
        $_SESSION["il_map_area_nr"] = "";
        $_SESSION["il_map_edit_coords"] = "";
        $_SESSION["il_map_edit_mode"] = "";
        $_SESSION["il_map_el_href"] = "";
        $_SESSION["il_map_il_type"] = "";
        $_SESSION["il_map_il_ltype"] = "";
        $_SESSION["il_map_il_target"] = "";
        $_SESSION["il_map_il_targetframe"] = "";
        $_SESSION["il_map_edit_area_type"] = "";
    }
    
    public function addArea(
        bool $a_handle = true
    ) : string {

        // handle map parameters
        if ($a_handle) {
            $this->handleMapParameters();
        }

        $area_type = $_SESSION["il_map_edit_area_type"];
        $coords = $_SESSION["il_map_edit_coords"];
        $cnt_coords = ilMapArea::countCoords($coords);

        // decide what to do next
        switch ($area_type) {
            // Rectangle
            case "Rect":
                if ($cnt_coords < 2) {
                    $html = $this->editMapArea(true, false, false);
                    return $html;
                } elseif ($cnt_coords == 2) {
                    return $this->editMapArea(false, true, true);
                }
                break;

            // Circle
            case "Circle":
                if ($cnt_coords <= 1) {
                    return $this->editMapArea(true, false, false);
                } else {
                    if ($cnt_coords == 2) {
                        $c = explode(",", $coords);
                        $coords = $c[0] . "," . $c[1] . ",";	// determine radius
                        $coords .= round(sqrt(pow(abs($c[3] - $c[1]), 2) + pow(abs($c[2] - $c[0]), 2)));
                    }
                    $_SESSION["il_map_edit_coords"] = $coords;

                    return $this->editMapArea(false, true, true);
                }
                break;

            // Polygon
            case "Poly":
                if ($cnt_coords < 1) {
                    return $this->editMapArea(true, false, false);
                } elseif ($cnt_coords < 3) {
                    return $this->editMapArea(true, true, false);
                } else {
                    return $this->editMapArea(true, true, true);
                }
                break;

            // Whole picture
            case "WholePicture":
                return $this->editMapArea(false, false, true);
                break;
        }
        return "";
    }

    /**
     * Edit a single map area
     * @param bool   $a_get_next_coordinate enable next coordinate input
     * @param bool   $a_output_new_area     output the new area
     * @param bool   $a_save_form           output save form
     * @param string $a_edit_property       "" | "link" | "shape"
     * @param int    $a_area_nr
     * @return string
     */
    public function editMapArea(
        bool $a_get_next_coordinate = false,
        bool $a_output_new_area = false,
        bool $a_save_form = false,
        string $a_edit_property = "",
        int $a_area_nr = 0
    ) : string {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        $area_type = $_SESSION["il_map_edit_area_type"];
        $coords = $_SESSION["il_map_edit_coords"];
        $cnt_coords = ilMapArea::countCoords($coords);

        $this->tpl = new ilTemplate("tpl.map_edit.html", true, true, "Services/MediaObjects");

        $this->tpl->setVariable("FORMACTION", $ilCtrl->getFormAction($this));

        if ($a_edit_property != "link") {
            switch ($area_type) {
                // rectangle
                case "Rect":
                    if ($cnt_coords == 0) {
                        ilUtil::sendInfo($lng->txt("cont_click_tl_corner"));
                    }
                    if ($cnt_coords == 1) {
                        ilUtil::sendInfo($lng->txt("cont_click_br_corner"));
                    }
                    break;

                // circle
                case "Circle":
                    if ($cnt_coords == 0) {
                        ilUtil::sendInfo($lng->txt("cont_click_center"));
                    }
                    if ($cnt_coords == 1) {
                        ilUtil::sendInfo($lng->txt("cont_click_circle"));
                    }
                    break;

                // polygon
                case "Poly":
                    if ($cnt_coords == 0) {
                        ilUtil::sendInfo($lng->txt("cont_click_starting_point"));
                    } elseif ($cnt_coords < 3) {
                        ilUtil::sendInfo($lng->txt("cont_click_next_point"));
                    } else {
                        ilUtil::sendInfo($lng->txt("cont_click_next_or_save"));
                    }
                    break;
            }
        }


        // map properties input fields (name and link)
        if ($a_save_form) {
            if ($a_edit_property != "shape") {
                // prepare link gui
                $ilCtrl->setParameter($this, "linkmode", "map");
                $this->tpl->setCurrentBlock("int_link_prep");
                $this->tpl->setVariable("INT_LINK_PREP", ilInternalLinkGUI::getInitHTML(
                    $ilCtrl->getLinkTargetByClass(
                        "ilinternallinkgui",
                        "",
                        false,
                        true,
                        false
                    )
                ));
                $this->tpl->parseCurrentBlock();
            }
            $form = $this->initAreaEditingForm($a_edit_property);
            $this->tpl->setVariable("FORM", $form->getHTML());
        }
        
        $this->makeMapWorkCopy(
            $a_edit_property,
            $a_area_nr,
            $a_output_new_area,
            $area_type,
            $coords
        );
        
        $edit_mode = ($a_get_next_coordinate)
            ? "get_coords"
            : (($a_output_new_area)
                ? "new_area"
                :"");
        $output = $this->getImageMapOutput($edit_mode);
        $this->tpl->setVariable("IMAGE_MAP", $output);

        return $this->tpl->get();
    }
    
    public function initAreaEditingForm(
        string $a_edit_property
    ) : ilPropertyFormGUI {
        $lng = $this->lng;

        $form = new ilPropertyFormGUI();
        $form->setOpenTag(false);
        $form->setCloseTag(false);
        
        // link
        if ($a_edit_property != "shape") {
            //
            $radg = new ilRadioGroupInputGUI($lng->txt("cont_link"), "area_link_type");
            if ($_SESSION["il_map_il_ltype"] != "int") {
                if ($_SESSION["il_map_el_href"] == "") {
                    $radg->setValue("no");
                } else {
                    $radg->setValue("ext");
                }
            } else {
                $radg->setValue("int");
            }
            
            // external link
            $ext = new ilRadioOption($lng->txt("cont_link_ext"), "ext");
            $radg->addOption($ext);
            
            $ti = new ilTextInputGUI("", "area_link_ext");
            $ti->setMaxLength(800);
            $ti->setSize(50);
            if ($_SESSION["il_map_el_href"] != "") {
                $ti->setValue($_SESSION["il_map_el_href"]);
            } else {
                $ti->setValue("http://");
            }
            $ext->addSubItem($ti);
            
            // internal link
            $int = new ilRadioOption($lng->txt("cont_link_int"), "int");
            $radg->addOption($int);
            
            $ne = new ilNonEditableValueGUI("", "", true);
            $link_str = "";
            if ($_SESSION["il_map_il_target"] != "") {
                $link_str = $this->getMapAreaLinkString(
                    $_SESSION["il_map_il_target"],
                    $_SESSION["il_map_il_type"],
                    $_SESSION["il_map_il_targetframe"]
                );
            }
            $ne->setValue(
                $link_str .
                    '&nbsp;<a id="iosEditInternalLinkTrigger" href="#">' .
                    "[" . $lng->txt("cont_get_link") . "]" .
                    '</a>'
            );
            $int->addSubItem($ne);
                
            // no link
            $no = new ilRadioOption($lng->txt("cont_link_no"), "no");
            $radg->addOption($no);
            
            $form->addItem($radg);
        }

        
        // name
        if ($a_edit_property != "link" && $a_edit_property != "shape") {
            $ti = new ilTextInputGUI($lng->txt("cont_name"), "area_name");
            $ti->setMaxLength(200);
            $ti->setSize(20);
            $form->addItem($ti);
        }
        
        // save and cancel commands
        if ($a_edit_property == "") {
            $form->setTitle($lng->txt("cont_new_area"));
            $form->addCommandButton("saveArea", $lng->txt("save"));
        } else {
            $form->setTitle($lng->txt("cont_new_area"));
            $form->addCommandButton("saveArea", $lng->txt("save"));
        }
                    
        //		$form->setFormAction($ilCtrl->getFormAction($this));
        
        return $form;
    }
    
    /**
     * Make work file for editing
     */
    public function makeMapWorkCopy(
        string $a_edit_property = "",
        int $a_area_nr = 0,
        bool $a_output_new_area = false,
        string $a_area_type = "",
        string $a_coords = ""
    ) : void {
        // create/update imagemap work copy
        $st_item = $this->media_object->getMediaItem("Standard");

        if ($a_edit_property == "shape") {
            $st_item->makeMapWorkCopy($a_area_nr, true);	// exclude area currently being edited
        } else {
            $st_item->makeMapWorkCopy($a_area_nr, false);
        }

        if ($a_output_new_area) {
            $st_item->addAreaToMapWorkCopy($a_area_type, $a_coords);
        }
    }
    
    /**
     * Render the image map.
     */
    public function getImageMapOutput(
        string $a_map_edit_mode = ""
    ) : string {
        $ilCtrl = $this->ctrl;
        
        $st_item = $this->media_object->getMediaItem("Standard");
        
        // output image map
        $xml = "<dummy>";
        $xml .= $this->getAliasXML();
        $xml .= $this->media_object->getXML(IL_MODE_OUTPUT);
        $xml .= $this->getAdditionalPageXML();
        $xml .= "</dummy>";
        $xsl = file_get_contents("./Services/COPage/xsl/page.xsl");
        //echo htmlentities($xml); exit;
        $args = array( '/_xml' => $xml, '/_xsl' => $xsl );
        $xh = xslt_create();
        $wb_path = ilUtil::getWebspaceDir("output") . "/";
        $mode = "media";
        //echo htmlentities($ilCtrl->getLinkTarget($this, "showImageMap"));

        $random = new \ilRandom();
        $params = array('map_edit_mode' => $a_map_edit_mode,
            'map_item' => $st_item->getId(),
            'map_mob_id' => $this->media_object->getId(),
            'mode' => $mode,
            'media_mode' => 'enable',
            'image_map_link' => $ilCtrl->getLinkTarget($this, "showImageMap", "", false, false),
            'link_params' => "ref_id=" . $_GET["ref_id"] . "&rand=" . $random->int(1, 999999),
            'ref_id' => $_GET["ref_id"],
            'pg_frame' => "",
            'enlarge_path' => ilUtil::getImagePath("enlarge.svg"),
            'webspace_path' => $wb_path);
        $output = xslt_process($xh, "arg:/_xml", "arg:/_xsl", null, $args, $params);
        echo xslt_error($xh);
        xslt_free($xh);
        
        $output = $this->outputPostProcessing($output);
        
        return $output;
    }
    
    /**
     * Get additional page xml (to be overwritten)
     * @return string additional page xml
     */
    public function getAdditionalPageXML() : string
    {
        return "";
    }
    
    public function outputPostProcessing(
        string $a_output
    ) : string {
        return $a_output;
    }

    public function getAliasXML() : string
    {
        return $this->media_object->getXML(IL_MODE_ALIAS);
    }

    /**
     * Get text name of internal link
     * @param	string		$a_target		target object link id
     * @param	string		$a_type			type
     * @param	string		$a_frame		target frame
     */
    public function getMapAreaLinkString(
        string $a_target,
        string $a_type,
        string $a_frame
    ) : string {
        $lng = $this->lng;
        
        $t_arr = explode("_", $a_target);
        if ($a_frame != "") {
            $frame_str = " (" . $a_frame . " Frame)";
        }
        switch ($a_type) {
            case "StructureObject":
                $title = ilLMObject::_lookupTitle($t_arr[count($t_arr) - 1]);
                $link_str = $lng->txt("chapter") .
                    ": " . $title . " [" . $t_arr[count($t_arr) - 1] . "]" . $frame_str;
                break;

            case "PageObject":
                $title = ilLMObject::_lookupTitle($t_arr[count($t_arr) - 1]);
                $link_str = $lng->txt("page") .
                    ": " . $title . " [" . $t_arr[count($t_arr) - 1] . "]" . $frame_str;
                break;

            case "GlossaryItem":
                $term = new ilGlossaryTerm($t_arr[count($t_arr) - 1]);
                $link_str = $lng->txt("term") .
                    ": " . $term->getTerm() . " [" . $t_arr[count($t_arr) - 1] . "]" . $frame_str;
                break;

            case "MediaObject":
                $mob = new ilObjMediaObject($t_arr[count($t_arr) - 1]);
                $link_str = $lng->txt("mob") .
                    ": " . $mob->getTitle() . " [" . $t_arr[count($t_arr) - 1] . "]" . $frame_str;
                break;
                
            case "RepositoryItem":
                $title = ilObject::_lookupTitle(
                    ilObject::_lookupObjId($t_arr[count($t_arr) - 1])
                );
                $link_str = $lng->txt("obj_" . $t_arr[count($t_arr) - 2]) .
                    ": " . $title . " [" . $t_arr[count($t_arr) - 1] . "]" . $frame_str;
                break;
        }

        return $link_str;
    }

    /**
     * Get image map coordinates.
     */
    public function editImagemapForward() : void
    {
        ilImageMapEditorGUI::_recoverParameters();

        if ($_SESSION["il_map_edit_coords"] != "") {
            $_SESSION["il_map_edit_coords"] .= ",";
        }

        $_SESSION["il_map_edit_coords"] .= $_POST["editImagemapForward_x"] . "," .
            $_POST["editImagemapForward_y"];

        // call editing script
        ilUtil::redirect($_SESSION["il_map_edit_target_script"]);
    }

    /**
     * Recover parameters from session variables (static)
     */
    public static function _recoverParameters() : void
    {
        $_GET["ref_id"] = $_SESSION["il_map_edit_ref_id"];
        $_GET["obj_id"] = $_SESSION["il_map_edit_obj_id"];
        $_GET["hier_id"] = $_SESSION["il_map_edit_hier_id"];
        $_GET["pc_id"] = $_SESSION["il_map_edit_pc_id"];
    }

    /**
     * Save new or updated map area
     */
    public function saveArea() : string
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        switch ($_SESSION["il_map_edit_mode"]) {
            // save edited link
            case "edit_link":
                $st_item = $this->media_object->getMediaItem("Standard");
                $max = ilMapArea::_getMaxNr($st_item->getId());
                $area = new ilMapArea($st_item->getId(), $_SESSION["il_map_area_nr"]);

                if ($_POST["area_link_type"] == IL_INT_LINK) {
                    $area->setLinkType(IL_INT_LINK);
                    $area->setType($_SESSION["il_map_il_type"]);
                    $area->setTarget($_SESSION["il_map_il_target"]);
                    $area->setTargetFrame($_SESSION["il_map_il_targetframe"]);
                } else {
                    $area->setLinkType(IL_EXT_LINK);
                    if ($_POST["area_link_type"] != IL_NO_LINK) {
                        $area->setHref(ilUtil::stripSlashes($_POST["area_link_ext"]));
                    } else {
                        $area->setHref("");
                    }
                }
                $area->update();
                break;

            // save edited shape
            case "edit_shape":
                $st_item = $this->media_object->getMediaItem("Standard");
                $max = ilMapArea::_getMaxNr($st_item->getId());
                $area = new ilMapArea($st_item->getId(), $_SESSION["il_map_area_nr"]);

                $area->setShape($_SESSION["il_map_edit_area_type"]);
                $area->setCoords($_SESSION["il_map_edit_coords"]);
                $area->update();
                break;

            // save new area
            default:
                $area_type = $_SESSION["il_map_edit_area_type"];
                $coords = $_SESSION["il_map_edit_coords"];

                $st_item = $this->media_object->getMediaItem("Standard");
                $max = ilMapArea::_getMaxNr($st_item->getId());

                // make new area object
                $area = new ilMapArea();
                $area->setItemId($st_item->getId());
                $area->setShape($area_type);
                $area->setCoords($coords);
                $area->setNr($max + 1);
                $area->setTitle(ilUtil::stripSlashes($_POST["area_name"]));
                switch ($_POST["area_link_type"]) {
                    case "ext":
                        $area->setLinkType(IL_EXT_LINK);
                        $area->setHref($_POST["area_link_ext"]);
                        break;

                    case "int":
                        $area->setLinkType(IL_INT_LINK);
                        $area->setType($_SESSION["il_map_il_type"]);
                        $area->setTarget($_SESSION["il_map_il_target"]);
                        $area->setTargetFrame($_SESSION["il_map_il_targetframe"]);
                        break;
                }

                // put area into item and update media object
                $st_item->addMapArea($area);
                $this->media_object->update();
                break;
        }

        //$this->initMapParameters();
        ilUtil::sendSuccess($lng->txt("cont_saved_map_area"), true);
        $ilCtrl->redirect($this, "editMapAreas");
        return "";
    }

    public function setInternalLink() : string
    {
        $_SESSION["il_map_il_type"] = $_GET["linktype"];
        $_SESSION["il_map_il_ltype"] = "int";

        $_SESSION["il_map_il_target"] = $_GET["linktarget"];
        $_SESSION["il_map_il_targetframe"] = $_GET["linktargetframe"];
        $_SESSION["il_map_il_anchor"] = $_GET["linkanchor"];
        switch ($_SESSION["il_map_edit_mode"]) {
            case "edit_link":
                return $this->setLink();

            default:
                return $this->addArea();
        }
    }
    
    public function setLink(
        bool $a_handle = true
    ) : string {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        if ($a_handle) {
            $this->handleMapParameters();
        }
        if ($_SESSION["il_map_area_nr"] != "") {
            $_POST["area"][0] = $_SESSION["il_map_area_nr"];
        }
        if (!isset($_POST["area"])) {
            ilUtil::sendFailure($lng->txt("no_checkbox"), true);
            $ilCtrl->redirect($this, "editMapAreas");
        }

        if (count($_POST["area"]) > 1) {
            //$this->ilias->raiseError($this->lng->txt("cont_select_max_one_item"),$this->ilias->error_obj->MESSAGE);
            ilUtil::sendFailure($lng->txt("cont_select_max_one_item"), true);
            $ilCtrl->redirect($this, "editMapAreas");
        }


        if ($_SESSION["il_map_edit_mode"] != "edit_link") {
            $_SESSION["il_map_area_nr"] = $_POST["area"][0];
            $_SESSION["il_map_il_ltype"] = $this->getLinkTypeOfArea($_POST["area"][0]);
            $_SESSION["il_map_edit_mode"] = "edit_link";
            $_SESSION["il_map_edit_target_script"] = $ilCtrl->getLinkTarget($this, "setLink");
            if ($_SESSION["il_map_il_ltype"] == IL_INT_LINK) {
                $_SESSION["il_map_il_type"] = $this->getTypeOfArea($_POST["area"][0]);
                $_SESSION["il_map_il_target"] = $this->getTargetOfArea($_POST["area"][0]);
                $_SESSION["il_map_il_targetframe"] = $this->getTargetFrameOfArea($_POST["area"][0]);
            } else {
                $_SESSION["il_map_el_href"] = $this->getHrefOfArea($_POST["area"][0]);
            }
        }

        return $this->editMapArea(false, false, true, "link", $_POST["area"][0]);
    }

    public function getLinkTypeOfArea(
        int $a_nr
    ) : string {
        $st_item = $this->media_object->getMediaItem("Standard");
        $area = $st_item->getMapArea($a_nr);
        return $area->getLinkType();
    }

    /**
     * Get Type of Area (only internal link)
     */
    public function getTypeOfArea(
        int $a_nr
    ) : string {
        $st_item = $this->media_object->getMediaItem("Standard");
        $area = $st_item->getMapArea($a_nr);
        return $area->getType();
    }

    /**
     * Get Target of Area (only internal link)
     */
    public function getTargetOfArea(
        int $a_nr
    ) : string {
        $st_item = $this->media_object->getMediaItem("Standard");
        $area = $st_item->getMapArea($a_nr);
        return $area->getTarget();
    }

    /**
     * Get TargetFrame of Area (only internal link)
     */
    public function getTargetFrameOfArea(
        int $a_nr
    ) : string {
        $st_item = $this->media_object->getMediaItem("Standard");
        $area = $st_item->getMapArea($a_nr);
        return $area->getTargetFrame();
    }

    /**
     * Get Href of Area (only external link)
     */
    public function getHrefOfArea(
        int $a_nr
    ) : string {
        $st_item = $this->media_object->getMediaItem("Standard");
        $area = $st_item->getMapArea($a_nr);
        return $area->getHref();
    }

    /**
     * Delete map areas
     */
    public function deleteAreas() : void
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        
        if (!isset($_POST["area"])) {
            ilUtil::sendFailure($lng->txt("no_checkbox"), true);
            $ilCtrl->redirect($this, "editMapAreas");
        }

        $st_item = $this->media_object->getMediaItem("Standard");
        $max = ilMapArea::_getMaxNr($st_item->getId());

        if (count($_POST["area"]) > 0) {
            $i = 0;

            foreach ($_POST["area"] as $area_nr) {
                $st_item->deleteMapArea($area_nr - $i);
                $i++;
            }

            $this->media_object->update();
            ilUtil::sendSuccess($lng->txt("cont_areas_deleted"), true);
        }

        $ilCtrl->redirect($this, "editMapAreas");
    }

    /**
     * Edit existing link
     */
    public function editLink() : string
    {
        $_SESSION["il_map_edit_coords"] = "";
        $_SESSION["il_map_edit_mode"] = "";
        $_SESSION["il_map_el_href"] = "";
        $_SESSION["il_map_il_type"] = "";
        $_SESSION["il_map_il_ltype"] = "";
        $_SESSION["il_map_il_target"] = "";
        $_SESSION["il_map_il_targetframe"] = "";
        $_SESSION["il_map_area_nr"] = "";
        return $this->setLink(false);
    }

    /**
     * Edit an existing shape (make it a whole picture link)
     */
    public function editShapeWholePicture() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "WholePicture";
        return $this->setShape(false);
    }

    /**
     * Edit an existing shape (make it a rectangle)
     */
    public function editShapeRectangle() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Rect";
        return $this->setShape(false);
    }

    /**
     * Edit an existing shape (make it a circle)
     */
    public function editShapeCircle() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Circle";
        return $this->setShape(false);
    }

    /**
     * Edit an existing shape (make it a polygon)
     */
    public function editShapePolygon() : string
    {
        $this->clearSessionVars();
        $_SESSION["il_map_edit_area_type"] = "Poly";
        return $this->setShape(false);
    }

    /**
     * edit shape of existing map area
     */
    public function setShape(
        bool $a_handle = true
    ) : string {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        if ($a_handle) {
            $this->handleMapParameters();
        }
        if ($_POST["areatype2"] != "") {
            $_SESSION["il_map_edit_area_type"] = $_POST["areatype2"];
        }
        if ($_SESSION["il_map_area_nr"] != "") {
            $_POST["area"][0] = $_SESSION["il_map_area_nr"];
        }
        if (!isset($_POST["area"])) {
            ilUtil::sendFailure($lng->txt("no_checkbox"), true);
            $ilCtrl->redirect($this, "editMapAreas");
        }

        if (count($_POST["area"]) > 1) {
            ilUtil::sendFailure($lng->txt("cont_select_max_one_item"), true);
            $ilCtrl->redirect($this, "editMapAreas");
        }

        if ($_SESSION["il_map_edit_mode"] != "edit_shape") {
            $_SESSION["il_map_area_nr"] = $_POST["area"][0];
            $_SESSION["il_map_edit_mode"] = "edit_shape";
            $_SESSION["il_map_edit_target_script"] = $ilCtrl->getLinkTarget($this, "setShape", "", false, false);
        }


        $area_type = $_SESSION["il_map_edit_area_type"];
        $coords = $_SESSION["il_map_edit_coords"];
        $cnt_coords = ilMapArea::countCoords($coords);

        // decide what to do next
        switch ($area_type) {
            // Rectangle
            case "Rect":
                if ($cnt_coords < 2) {
                    return $this->editMapArea(true, false, false, "shape", $_POST["area"][0]);
                } elseif ($cnt_coords == 2) {
                    return $this->saveArea();
                }
                break;

            // Circle
            case "Circle":
                if ($cnt_coords <= 1) {
                    return $this->editMapArea(true, false, false, "shape", $_POST["area"][0]);
                } else {
                    if ($cnt_coords == 2) {
                        $c = explode(",", $coords);
                        $coords = $c[0] . "," . $c[1] . ",";	// determine radius
                        $coords .= round(sqrt(pow(abs($c[3] - $c[1]), 2) + pow(abs($c[2] - $c[0]), 2)));
                    }
                    $_SESSION["il_map_edit_coords"] = $coords;

                    return $this->saveArea();
                }
                break;

            // Polygon
            case "Poly":
                if ($cnt_coords < 1) {
                    return $this->editMapArea(true, false, false, "shape", $_POST["area"][0]);
                } elseif ($cnt_coords < 3) {
                    return $this->editMapArea(true, true, false, "shape", $_POST["area"][0]);
                } else {
                    return $this->editMapArea(true, true, true, "shape", $_POST["area"][0]);
                }
                break;
            
            // Whole Picture
            case "WholePicture":
                return $this->saveArea();
        }
        return "";
    }

    /**
     * Set highlight settings
     */
    public function setHighlight() : void
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        
        $st_item = $this->media_object->getMediaItem("Standard");
        $st_item->setHighlightMode(ilUtil::stripSlashes($_POST["highlight_mode"]));
        $st_item->setHighlightClass(ilUtil::stripSlashes($_POST["highlight_class"]));
        $st_item->update();
        
        ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
        $ilCtrl->redirect($this, "editMapAreas");
    }
}
