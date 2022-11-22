<?php

namespace SilverStripers\UDFSpamProtection\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use SilverStripers\UDFSpamProtection\Control\UserDefinedFormController;

class SubmittedFormExtension extends DataExtension
{

    private static $db = [
        'IPAddress' => 'Varchar',
        'MarkedAsSpam' => 'Boolean',
        'ReasonForSpam' => 'Text'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'MarkedAsSpam',
            'IPAddress',
            'ReasonForSpam'
        ]);
        $fields->insertBefore('Values', TextareaField::create('ReasonForSpam')->setReadonly(true));
    }

    public function onBeforeWrite()
    {
        $form = $this->owner;
        if (empty($form->IPAddress) && Controller::has_curr() && ($controller = Controller::curr())) {
            $form->IPAddress = $controller->getRequest()->getIP();
        }
    }
}
