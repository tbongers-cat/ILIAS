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

use ILIAS\Filesystem\Filesystem;
use ILIAS\FileUpload\FileUpload;
use ILIAS\Filesystem\Exception\FileAlreadyExistsException;
use ILIAS\Filesystem\Exception\FileNotFoundException;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\Location;

/**
 * GUI class to create PDF certificates
 * @author        Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @version       $Id$
 * @ingroup       Services
 * @ilCtrl_Calls  ilCertificateGUI: ilPropertyFormGUI
 */
class ilCertificateGUI
{
    private readonly ilCertificateBackgroundImageDelete $backgroundImageDelete;
    private readonly Filesystem $fileSystem;
    private readonly WrapperFactory $httpWrapper;
    private readonly Factory $refinery;
    protected ilCtrlInterface $ctrl;
    protected ilTree $tree;
    protected ILIAS $ilias;
    protected ilGlobalPageTemplate $tpl;
    protected ilLanguage $lng;
    protected int $ref_id;
    protected ilAccessHandler $access;
    protected ilToolbarGUI $toolbar;
    private readonly ilCertificateTemplateRepository $templateRepository;
    private readonly ilCertificateFormRepository $settingsFormFactory;
    private readonly ilXlsFoParser $xlsFoParser;
    private readonly ilCertificateDeleteAction $deleteAction;
    private readonly ilCertificateTemplateExportAction $exportAction;
    private readonly ilCertificateBackgroundImageUpload $backgroundImageUpload;
    private readonly ilCertificateTemplatePreviewAction $previewAction;
    private readonly FileUpload $fileUpload;
    private readonly string $certificatePath;
    private readonly ilPageFormats $pageFormats;
    private readonly Filesystem $tmp_file_system;
    private readonly ilLogger $logger;
    private readonly ilObjCertificateSettings $global_certificate_settings;

    public function __construct(
        private readonly ilCertificatePlaceholderDescription $placeholderDescriptionObject,
        ilCertificatePlaceholderValues $placeholderValuesObject,
        private readonly int $objectId,
        string $certificatePath,
        ?ilCertificateFormRepository $settingsFormFactory = null,
        ?ilCertificateDeleteAction $deleteAction = null,
        ?ilCertificateTemplateRepository $templateRepository = null,
        ?ilPageFormats $pageFormats = null,
        ?ilXlsFoParser $xlsFoParser = null,
        ?ilCertificateTemplateExportAction $exportAction = null,
        ?ilCertificateBackgroundImageUpload $upload = null,
        ?ilCertificateTemplatePreviewAction $previewAction = null,
        ?FileUpload $fileUpload = null,
        private readonly ilSetting $settings = new ilSetting('certificate'),
        ?ilCertificateBackgroundImageDelete $backgroundImageDelete = null,
        ?Filesystem $fileSystem = null,
        ?ilCertificateBackgroundImageFileService $imageFileService = null,
        ?Filesystem $tmp_file_system = null
    ) {
        global $DIC;

        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
        $this->lng = $DIC['lng'];
        $this->tpl = $DIC['tpl'];
        $this->ctrl = $DIC['ilCtrl'];
        $this->ilias = $DIC['ilias'];

        $this->tree = $DIC['tree'];
        $this->access = $DIC['ilAccess'];
        $this->toolbar = $DIC['ilToolbar'];

        $this->global_certificate_settings = new ilObjCertificateSettings();

        $this->lng->loadLanguageModule('cert');
        $this->lng->loadLanguageModule("trac");

        $this->ref_id = (int) $DIC->http()->wrapper()->query()->retrieve("ref_id", $DIC->refinery()->kindlyTo()->int());

        $this->logger = $DIC->logger()->cert();

        $this->settingsFormFactory = $settingsFormFactory ?? new ilCertificateSettingsFormRepository(
            $this->objectId,
            $certificatePath,
            $this->lng,
            $this->tpl,
            $this->ctrl,
            $this->access,
            $this->toolbar,
            $placeholderDescriptionObject,
            $DIC->ui()->factory(),
            $DIC->ui()->renderer()
        );
        $this->templateRepository = $templateRepository ?? new ilCertificateTemplateDatabaseRepository(
            $DIC->database(),
            $this->logger
        );
        $this->deleteAction = $deleteAction ?? new ilCertificateTemplateDeleteAction($this->templateRepository);
        $this->pageFormats = $pageFormats ?? new ilPageFormats($DIC->language());
        $this->xlsFoParser = $xlsFoParser ?? new ilXlsFoParser($DIC->settings(), $this->pageFormats);
        $this->backgroundImageUpload = $upload ?? new ilCertificateBackgroundImageUpload(
            $DIC->upload(),
            $certificatePath,
            $DIC->language()
        );
        $this->exportAction = $exportAction ?? new ilCertificateTemplateExportAction(
            $this->objectId,
            $certificatePath,
            $this->templateRepository,
            $DIC->filesystem()->web()
        );
        $this->previewAction = $previewAction ?? new ilCertificateTemplatePreviewAction(
            $this->templateRepository,
            $placeholderValuesObject
        );
        $this->fileUpload = $fileUpload ?? $DIC->upload();
        $this->certificatePath = $certificatePath;
        $this->fileSystem = $fileSystem ?? $DIC->filesystem()->web();
        $imageFileService ??= new ilCertificateBackgroundImageFileService(
            $this->certificatePath,
            $this->fileSystem
        );
        $this->backgroundImageDelete = $backgroundImageDelete ?? new ilCertificateBackgroundImageDelete(
            $this->certificatePath,
            $imageFileService
        );
        $this->tmp_file_system = $tmp_file_system ?? $DIC->filesystem()->temp();
        ;
    }

    /**
     * @return mixed|null
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilWACException
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass($this);

        $ret = null;

        $cmd = $this->getCommand($cmd);
        switch ($next_class) {
            case 'ilpropertyformgui':
                $form = $this->getEditorForm();
                $this->ctrl->forwardCommand($form);
                break;

            default:
                $ret = $this->$cmd();
                break;
        }
        return $ret;
    }

    public function getCommand($cmd)
    {
        return $cmd;
    }

    public function certificateImport(): void
    {
        $this->certificateEditor();
    }

    public function certificatePreview(): void
    {
        try {
            $this->previewAction->createPreviewPdf($this->objectId);
        } catch (Exception) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('error_creating_certificate_pdf'));
            $this->certificateEditor();
        }
    }

    /**
     * Exports the certificate
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function certificateExportFO(): void
    {
        $this->exportAction->export();
    }

    /**
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilObjectNotFoundException
     * @throws ilWACException
     */
    public function certificateRemoveBackground(): void
    {
        $this->backgroundImageDelete->deleteBackgroundImage(null);
        $this->certificateEditor();
    }

    public function certificateDelete(): void
    {
        // display confirmation message
        $cgui = new ilConfirmationGUI();
        $cgui->setFormAction($this->ctrl->getFormAction($this, "certificateEditor"));
        $cgui->setHeaderText($this->lng->txt("certificate_confirm_deletion_text"));
        $cgui->setCancel($this->lng->txt("no"), "certificateEditor");
        $cgui->setConfirm($this->lng->txt("yes"), "certificateDeleteConfirm");

        $this->tpl->setContent($cgui->getHTML());
    }

    /**
     * Deletes the certificate and all its data
     */
    public function certificateDeleteConfirm(): void
    {
        $template = $this->templateRepository->fetchCurrentlyUsedCertificate($this->objectId);
        $templateId = $template->getId();

        $this->deleteAction->delete($templateId, $this->objectId);
        $this->ctrl->redirect($this, "certificateEditor");
    }

    /**
     * Saves the certificate
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilWACException
     */
    public function certificateSave(): void
    {
        global $DIC;

        $form = $this->settingsFormFactory->createForm(
            $this
        );

        $form->setValuesByPost();

        $request = $DIC->http()->request();

        $formFields = $request->getParsedBody();

        $this->tpl->setVariable('ADM_CONTENT', $form->getHTML());

        $this->saveCertificate($form, $formFields, $this->objectId);
    }

    /**
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilObjectNotFoundException
     * @throws ilWACException
     */
    public function certificateUpload(): void
    {
        $this->certificateEditor();
    }

    /**
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilWACException
     */
    private function getEditorForm(): ilPropertyFormGUI
    {
        $certificateTemplate = $this->templateRepository->fetchCurrentlyUsedCertificate($this->objectId);

        $form = $this->settingsFormFactory->createForm(
            $this
        );

        $formFields = $this->createFormatArray($certificateTemplate);

        $formFields['active'] = $certificateTemplate->isCurrentlyActive();

        $form->setValuesByArray($formFields);

        return $form;
    }

    /**
     * Shows the certificate editor for ILIAS tests
     * @throws FileAlreadyExistsException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ilDatabaseException
     * @throws ilException
     * @throws ilObjectNotFoundException
     * @throws ilWACException
     */
    public function certificateEditor(): void
    {
        $form = $this->getEditorForm();
        $enabledGlobalLearningProgress = ilObjUserTracking::_enabledLearningProgress();

        $messageBoxHtml = '';
        if ($enabledGlobalLearningProgress) {
            $objectLearningProgressSettings = new ilLPObjSettings($this->objectId);
            $mode = $objectLearningProgressSettings->getMode();

            /** @var ilObject $object */
            $object = ilObjectFactory::getInstanceByObjId($this->objectId);
            if (ilLPObjSettings::LP_MODE_DEACTIVATED === $mode && $object->getType() !== 'crs') {
                global $DIC;

                $renderer = $DIC->ui()->renderer();
                $messageBox = $DIC->ui()
                                  ->factory()
                                  ->messageBox()
                                  ->info($this->lng->txt('learning_progress_deactivated'));

                $messageBoxHtml = $renderer->render($messageBox);
                $form->clearCommandButtons();
            }
        }

        $formHtml = $form->getHTML();

        $this->tpl->setVariable("ADM_CONTENT", $messageBoxHtml . $formHtml);
    }

    private function saveCertificate(ilPropertyFormGUI $form, array $form_fields, int $objId): void
    {
        $previousCertificateTemplate = $this->templateRepository->fetchPreviousCertificate($objId);
        $currentVersion = $previousCertificateTemplate->getVersion();
        $nextVersion = $currentVersion + 1;

        $backgroundDelete = $this->httpWrapper->post()->has("background_delete") && $this->httpWrapper->post()->retrieve(
            "background_delete",
            $this->refinery->kindlyTo()->bool()
        );
        $certificateCardThumbnailImageDelete = $this->httpWrapper->post()->has("certificate_card_thumbnail_image_delete") && $this->httpWrapper->post()->retrieve(
            "certificate_card_thumbnail_image_delete",
            $this->refinery->kindlyTo()->bool()
        );

        if ($backgroundDelete) {
            $this->backgroundImageDelete->deleteBackgroundImage($currentVersion);
        }

        if ($form->checkInput()) {
            try {
                $this->settingsFormFactory->save($form_fields);

                $templateValues = $this->placeholderDescriptionObject->getPlaceholderDescriptions();

                // handle the background upload
                $backgroundImagePath = '';
                $temporaryFileName = $_FILES['background']['tmp_name'];
                if ($temporaryFileName !== '') {
                    try {
                        $backgroundImagePath = $this->backgroundImageUpload->uploadBackgroundImage(
                            $temporaryFileName,
                            $nextVersion,
                            $form->getInput('background')
                        );
                    } catch (ilException) {
                        $form->getItemByPostVar('background')->setAlert($this->lng->txt("certificate_error_upload_bgimage"));
                    }
                    if (!$this->fileSystem->has($backgroundImagePath)) {
                        $form->getItemByPostVar('background')->setAlert($this->lng->txt("certificate_error_upload_bgimage"));
                        $backgroundImagePath = '';
                    }
                }
                if ($backgroundImagePath === '') {
                    if ($backgroundDelete || $previousCertificateTemplate->getBackgroundImagePath() === '') {
                        $backgroundImagePath = $this->global_certificate_settings->getDefaultBackgroundImagePath(true);
                    } else {
                        $backgroundImagePath = $previousCertificateTemplate->getBackgroundImagePath();
                    }
                }

                // handle the card thumbnail upload
                $cardThumbnailImagePath = '';
                $temporaryFileName = $_FILES['certificate_card_thumbnail_image']['tmp_name'];
                if ($temporaryFileName !== '' && $this->fileUpload->hasUploads()) {
                    try {
                        if (!$this->fileUpload->hasBeenProcessed()) {
                            $this->fileUpload->process();
                        }

                        $uploadResults = $this->fileUpload->getResults();
                        $pending_card_file = $form->getInput('certificate_card_thumbnail_image');
                        $cardThumbnailFileName = 'card_thumbnail_image_' . $nextVersion . '.svg';
                        if (isset($uploadResults[$temporaryFileName])) {
                            /** @var UploadResult $result */
                            $result = $uploadResults[$temporaryFileName];
                            if ($result->isOK()) {
                                $this->fileUpload->moveOneFileTo(
                                    $result,
                                    $this->certificatePath,
                                    Location::WEB,
                                    $cardThumbnailFileName,
                                    true
                                );

                                $cardThumbnailImagePath = $this->certificatePath . $cardThumbnailFileName;
                            }
                        } elseif (!empty($pending_card_file)) {
                            $stream = $this->tmp_file_system->readStream(basename($pending_card_file['tmp_name']));
                            $this->fileSystem->writeStream(
                                $this->certificatePath . '/' . $cardThumbnailFileName,
                                $stream
                            );
                            $cardThumbnailImagePath = $this->certificatePath . $cardThumbnailFileName;
                        } else {
                            throw new ilException($this->lng->txt('upload_error_file_not_found'));
                        }
                    } catch (ilException) {
                        $form->getItemByPostVar('certificate_card_thumbnail_image')->setAlert($this->lng->txt("certificate_error_upload_ctimage"));
                    }
                    if (!$this->fileSystem->has($cardThumbnailImagePath)) {
                        $form->getItemByPostVar('certificate_card_thumbnail_image')->setAlert($this->lng->txt("certificate_error_upload_ctimage"));
                        $cardThumbnailImagePath = '';
                    }
                }
                if ($cardThumbnailImagePath === '' && !$certificateCardThumbnailImageDelete) {
                    $cardThumbnailImagePath = $previousCertificateTemplate->getThumbnailImagePath();
                }

                $jsonEncodedTemplateValues = json_encode($templateValues, JSON_THROW_ON_ERROR);

                $xslfo = $this->xlsFoParser->parse($form_fields);

                $newHashValue = hash(
                    'sha256',
                    implode('', [
                        $xslfo,
                        $backgroundImagePath,
                        $jsonEncodedTemplateValues,
                        $cardThumbnailImagePath
                    ])
                );

                $active = (bool) ($form_fields['active'] ?? false);

                if ($newHashValue !== $previousCertificateTemplate->getCertificateHash()) {
                    $certificateTemplate = new ilCertificateTemplate(
                        $objId,
                        ilObject::_lookupType($objId),
                        $xslfo,
                        $newHashValue,
                        $jsonEncodedTemplateValues,
                        $nextVersion,
                        ILIAS_VERSION_NUMERIC,
                        time(),
                        $active,
                        $backgroundImagePath,
                        $cardThumbnailImagePath
                    );

                    $this->templateRepository->save($certificateTemplate);
                    $this->tpl->setOnScreenMessage('success', $this->lng->txt("saved_successfully"), true);
                    $this->ctrl->redirect($this, "certificateEditor");
                }

                if ($previousCertificateTemplate->getId() !== null && $previousCertificateTemplate->isCurrentlyActive() !== $active) {
                    $this->templateRepository->updateActivity($previousCertificateTemplate, $active);
                    $this->tpl->setOnScreenMessage('info', $this->lng->txt('certificate_change_active_status'), true);
                    $this->ctrl->redirect($this, "certificateEditor");
                }

                $this->tpl->setOnScreenMessage('info', $this->lng->txt('certificate_same_not_saved'), true);
                $this->ctrl->redirect($this, "certificateEditor");
            } catch (Exception $e) {
                $this->tpl->setOnScreenMessage(
                    'failure',
                    $e->getMessage()
                );
                $this->logger->error($e->getTraceAsString());
            }
        }

        $form->setValuesByPost();

        $this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
    }

    private function createFormatArray(ilCertificateTemplate $certificateTemplate): array
    {
        if ('' === $certificateTemplate->getCertificateHash()) {
            $format = $this->settings->get('pageformat', '');
            $formats = $this->pageFormats->fetchPageFormats();

            return [
                'pageformat' => $format,
                'pagewidth' => $formats['width'] ?? '',
                'pageheight' => $formats['height'] ?? '',
                'margin_body_top' => ilPageFormats::DEFAULT_MARGIN_BODY_TOP,
                'margin_body_right' => ilPageFormats::DEFAULT_MARGIN_BODY_RIGHT,
                'margin_body_bottom' => ilPageFormats::DEFAULT_MARGIN_BODY_BOTTOM,
                'margin_body_left' => ilPageFormats::DEFAULT_MARGIN_BODY_LEFT,
                'certificate_text' => $certificateTemplate->getCertificateContent()
            ];
        }
        return $this->settingsFormFactory->fetchFormFieldData($certificateTemplate->getCertificateContent());
    }
}
