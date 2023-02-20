<?php

namespace SilverStripers\UDFSpamProtection\Utils;

use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\UserDefinedForm;
use SilverStripers\UDFSpamProtection\Extension\ConfigExtension;

class UDFSpamUtils extends SpamUtils
{

    private $udf = null;

    public function setUDF($udf)
    {
        $this->udf = $udf;
        return $this;
    }

    protected function isInvalidEmail($data) : bool
    {
        /* @var $form SubmittedForm */
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedEmailAddresses
            && ($emailsPatterns = $this->getComparisons($config->BlockedEmailAddresses))
            && ($emailFields = $this->udf->Fields()->filter('ClassName', EditableEmailField::class))
            && $emailFields->count();
        if ($check) {
            foreach ($emailsPatterns as $emailsPattern) {
                /* @var $emailField EditableEmailField */
                foreach ($emailFields as $emailField) {
                    if (!empty($data[$emailField->Name])) {
                        $value = $data[$emailField->Name];
                        if ($value && $this->matchWildcard($emailsPattern, $value)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    protected function isThrottled() : bool
    {
        if ($ip = $this->getIpAddress()) {
            $config = SiteConfig::current_site_config();
            if ($config->ThrottlingInterval) {
                $interval = $config->ThrottlingType == ConfigExtension::PER_SECOND ? $config->ThrottlingInterval : $config->ThrottlingInterval * 60;
                $date = date('Y-m-d H:i:s', strtotime(DBDatetime::now()->getValue()) - $interval);

                $submissions = SubmittedForm::get()
                    ->filter([
                        'IPAddress' => $ip,
                        'Created:GreaterThan' => $date
                    ])->count();
                if ($submissions) {
                    return true;
                }
            }
            return false;
        }
    }

}
