<?php

namespace SilverStripers\UDFSpamProtection\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
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
            'IPAddress'
        ]);
        $fields->dataFieldByName('ReasonForSpam')->setReadonly(true);
    }

    public function onBeforeWrite()
    {
        $form = $this->owner;
        if (empty($form->IPAddress) && Controller::has_curr() && ($controller = Controller::curr())) {
            $form->IPAddress = $controller->getRequest()->getIP();
        }
        parent::onBeforeWrite();
    }

    public function onAfterDataSaved()
    {
        [$isSpam, $spamMessage] = $this->isSpam();
        $this->owner->update([
            'MarkedAsSpam' => $isSpam,
            'ReasonForSpam' => $spamMessage
        ]);
        if ($isSpam) {
            UserDefinedFormExtension::set_processing_spam(true);
        }
        $this->owner->write();
    }

    protected function isSpam() : array
    {
        /* @var $form SubmittedForm */
        $form = $this->owner;
        $ret = false;
        $message = null;

        if ($this->isInvalidEmail()) {
            $ret = true;
            $message = 'Failed in the email checks';
        }

        if ($this->hasInvalidStrings()) {
            $ret = true;
            $message = 'Contains invalid characters or strings';
        }

        if ($this->isInvalidIPAddress()) {
            $ret = true;
            $message = 'From an invalid IP';
        }

        if ($this->isThrottled()) {
            $ret = true;
            $message = 'Throttled';
        }

        return [$ret, $message];
    }

    private function isThrottled()
    {
        $config = SiteConfig::current_site_config();
        if ($config->ThrottlingInterval) {
            $interval = $config->ThrottlingType == ConfigExtension::PER_SECOND ? $config->ThrottlingInterval : $config->ThrottlingInterval * 60;
            $date = date('Y-m-d H:i:s', strtotime(DBDatetime::now()->getValue()) - $interval);

            $submissions = SubmittedForm::get()
                ->filter([
                    'IPAddress' => $this->owner->IPAddress,
                    'Created:GreaterThan' => $date
                ])->count();
            if ($submissions) {
                return true;
            }
        }
        return false;
    }

    private function isInvalidIPAddress() : bool
    {
        /* @var $form SubmittedForm */
        $form = $this->owner;
        $parent = $form->Parent();
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedIPAddresses
            && ($ipAddresses = $this->getComparisons($config->BlockedIPAddresses));
        if ($check) {
            foreach ($ipAddresses as $ipAddress) {
                if ($form->IPAddress == $ipAddress) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isInvalidEmail() : bool
    {
        /* @var $form SubmittedForm */
        $form = $this->owner;
        $parent = $form->Parent();
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedEmailAddresses
            && ($emails = $this->getComparisons($config->BlockedEmailAddresses))
            && ($emailFields = $parent->Fields()->filter('ClassName', EditableEmailField::class))
            && $emailFields->count();
        if ($check) {
            foreach ($emails as $email) {
                foreach ($emailFields as $emailField) {
                    $value = SubmittedFormField::get()->filter([
                        'ParentID' => $this->owner->ID,
                        'Name' => $emailField->Name
                    ])->first();
                    if ($value && $this->matchWildcard($email, $value->Value)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function hasInvalidStrings() : bool
    {
        /* @var $form SubmittedForm */
        $form = $this->owner;
        $parent = $form->Parent();
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedKeywords
            && ($patterns = $this->getComparisons($config->BlockedKeywords));
        
        if ($check) {
            foreach ($patterns as $pattern) {
                $values = SubmittedFormField::get()->filter([
                    'ParentID' => $this->owner->ID
                ]);
                foreach ($values as $value) {
                    if ($this->matchWildcard($pattern, $value->Value)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function getComparisons($str) : array
    {
        $lines = explode(PHP_EOL, $str);
        return array_filter(array_map('trim', $lines));
    }

    private function matchWildcard($pattern, $string) : bool
    {
        $pattern = '#^' . $this->wildcardToRegex($pattern). '$#iu';
        return (bool) preg_grep($pattern, [$string]);
    }

    private function wildcardToRegex($pattern, $delimiter = '/') : string
    {
        $converted = preg_quote($pattern, $delimiter);
        $converted = str_replace('\*', '.*', $converted);
        return $converted;
    }

}
