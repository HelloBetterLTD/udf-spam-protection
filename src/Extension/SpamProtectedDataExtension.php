<?php

namespace SilverStripers\UDFSpamProtection\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataExtension;

class SpamProtectedDataExtension extends DataExtension
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
        $fields->addFieldsToTab(
            'Root.Spam',
            [
                CheckboxField::create('MarkedAsSpam', 'Marked as spam')
                    ->setReadonly(true),
                TextareaField::create('ReasonForSpam')
                    ->setReadonly(true)
            ]
        );
    }

    public function onBeforeWrite()
    {
        $form = $this->owner;
        if (empty($form->IPAddress) && Controller::has_curr() && ($controller = Controller::curr())) {
            $form->IPAddress = $controller->getRequest()->getIP();
        }
    }

}
