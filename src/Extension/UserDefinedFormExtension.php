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

}
