<?php

namespace SilverStripers\UDFSpamProtection\Control;

use Psr\Log\LoggerInterface;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Security;
use SilverStripe\UserForms\Control\UserDefinedFormController as SS_UserDefinedFormController;
use SilverStripe\UserForms\Extension\UserFormFileExtension;
use SilverStripe\UserForms\Model\EditableFormField\EditableFileField;
use SilverStripe\UserForms\Model\Submission\SubmittedFileField;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\View\SSViewer;
use SilverStripers\UDFSpamProtection\Utils\SpamUtils;

class UserDefinedFormController extends SS_UserDefinedFormController
{

    private static $allowed_actions = [
        'process'
    ];


    /**
     * Process the form that is submitted through the site
     *
     * {@see UserForm::validate()} for validation step prior to processing
     *
     * @param array $data
     * @param Form $form
     *
     * @return HTTPResponse
     */
    public function process($data, $form)
    {
        [$isSpam, $spamMessage] = SpamUtils::validate_submission($data, $this->data());
        if ($isSpam) { // save the spam submissions anyway as these might need to look at
            $submittedForm = SubmittedForm::create();
            $submittedForm->SubmittedByID = Security::getCurrentUser() ? Security::getCurrentUser()->ID : 0;
            $submittedForm->ParentClass = get_class($this->data());
            $submittedForm->ParentID = $this->ID;
            $submittedForm->MarkedAsSpam = true;
            $submittedForm->ReasonForSpam = $spamMessage;

            $submittedForm->write();

            foreach ($this->data()->Fields() as $field) {
                if (!$field->showInReports()) {
                    continue;
                }

                $submittedField = $field->getSubmittedFormField();
                $submittedField->ParentID = $submittedForm->ID;
                $submittedField->Name = $field->Name;
                $submittedField->Title = $field->getField('Title');

                // save the value from the data
                if ($field->hasMethod('getValueFromData')) {
                    $submittedField->Value = $field->getValueFromData($data);
                } else {
                    if (isset($data[$field->Name])) {
                        $submittedField->Value = $data[$field->Name];
                    }
                }

                // set visibility flag according to display rules
                $submittedField->Displayed = $field->isDisplayed($data);
                if (!empty($data[$field->Name])) {
                    if (in_array(EditableFileField::class, $field->getClassAncestry() ?? [])) {
                        if (!empty($_FILES[$field->Name]['name'])) {
                            $foldername = $field->getFormField()->getFolderName();

                            // create the file from post data
                            $upload = Upload::create();
                            try {
                                $upload->loadIntoFile($_FILES[$field->Name], null, $foldername);
                            } catch (ValidationException $e) {
                                $validationResult = $e->getResult();
                                foreach ($validationResult->getMessages() as $message) {
                                    $form->sessionMessage($message['message'], ValidationResult::TYPE_ERROR);
                                }
                                Controller::curr()->redirectBack();
                                return;
                            }
                            /** @var AssetContainer|File $file */
                            $file = $upload->getFile();
                            $file->ShowInSearch = 0;
                            $file->UserFormUpload = UserFormFileExtension::USER_FORM_UPLOAD_TRUE;
                            $file->write();

                            // generate image thumbnail to show in asset-admin
                            // you can run userforms without asset-admin, so need to ensure asset-admin is installed
                            if (class_exists(AssetAdmin::class)) {
                                AssetAdmin::singleton()->generateThumbnails($file);
                            }

                            // write file to form field
                            $submittedField->UploadedFileID = $file->ID;

                            // attach a file only if lower than 1MB
                            if ($file->getAbsoluteSize() < 1024 * 1024 * 1) {
                                $attachments[] = $file;
                            }
                        }
                    }
                }
                $submittedField->write();
            }

            // no emails sent, and redirect.
            $referrer = (isset($data['Referrer'])) ? '?referrer=' . urlencode($data['Referrer']) : "";
            return $this->redirect($this->Link('finished') . $referrer . $this->config()->get('finished_anchor'));
        }
        return parent::process($data, $form);
    }

}
