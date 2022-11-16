<?php

namespace SilverStripers\UDFSpamProtection\Extension;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

class ConfigExtension extends DataExtension
{

    const PER_MINUTE = 'PER_MINUTE';
    const PER_SECOND = 'PER_SECOND';

    private static $db = [
        'BlockedEmailAddresses' => 'Text',
        'BlockedKeywords' => 'Text',
        'BlockedIPAddresses' => 'Text',
        'ThrottlingInterval' => 'Int',
        'ThrottlingType' => 'Enum(array(' .
            "'" . self::PER_MINUTE . "'," .
            "'" . self::PER_SECOND . "'," .
            "), '" . self::PER_MINUTE . "')",
    ];

    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);
        $fields->addFieldsToTab('Root.SpamSettings', [
            HeaderField::create('SpamSettings', 'Spam Settings'),
            TextareaField::create('BlockedEmailAddresses', 'Block Email addresses')
                ->setDescription('
                    Enter any email addresses you would like to be blocked being used in any email fields.
                    Use asterisks for wildcards (e.g. *@hotmail.ru), use a new line for each email.
                '),
            TextareaField::create('BlockedKeywords', 'Block Keywords')
                ->setDescription('
                    Enter keywords you would like blocked from being used in all text and textarea fields.
                    Use quotes for phrases (e.g. "generate new leads"), asterisks for wildcards (e.g. lead*),
                    use a new line for each email. For individual characters partial words or strings,
                    use * characters on before and after.
                '),
            TextareaField::create('BlockedIPAddresses', 'Block IP addresses')
                ->setDescription('Enter IP addresses you would like blocked. Separate multiples on new lines.'),
            TextField::create('ThrottlingInterval', 'Throttling'),
            DropdownField::create('ThrottlingType', '')
                ->setSource([
                    self::PER_MINUTE => 'per minute',
                    self::PER_SECOND => 'per second',
                ])
        ]);
    }


}
