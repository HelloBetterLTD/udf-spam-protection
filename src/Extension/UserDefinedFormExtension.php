<?php

namespace SilverStripers\UDFSpamProtection\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;

class UserDefinedFormExtension extends DataExtension
{

    private static $is_processing_spam = false;

    public function updateCMSFields(FieldList $fields)
    {
        /* @var $submissionGrid GridField */
        $submissionGrid = $fields->dataFieldByName('Submissions');
        if ($submissionGrid) {
            $list = $submissionGrid->getList();
            $submissionGrid->setList($list->filter('MarkedAsSpam', 0));

            $submissionConfig = clone $submissionGrid->getConfig();
            $fields->addFieldsToTab('Root.SpamSubmissions', [
                GridField::create('SpamSubmissions')
                    ->setList($list->filter('MarkedAsSpam', 1))
                    ->setConfig($submissionConfig)
            ]);
        }
    }

    public static function set_processing_spam($processing)
    {
        self::$is_processing_spam = $processing;
    }

    public static function get_processing_spam()
    {
        return self::$is_processing_spam;
    }

    public function updateFilteredEmailRecipients(&$recipients, $data, $form)
    {
        if (self::get_processing_spam()) {
            $recipients = new ArrayList(); // its a spam dont send emails
        }
    }

}
