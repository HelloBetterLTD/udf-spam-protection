<?php

namespace SilverStripers\UDFSpamProtection\Utils;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\UserForms\Extension\UserFormFieldEditorExtension;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use SilverStripers\UDFSpamProtection\Extension\ConfigExtension;

class SpamUtils
{

    use Configurable;
    use Injectable;

    /**
     * @param $data
     * @param $udf UserFormFieldEditorExtension
     * @return array
     */
    public static function validate_submission($data, $udf) : array
    {
        $inst = Injector::inst()->get(self::class);
        return $inst->validate($data, $udf);
    }

    /**
     * @param $data
     * @param $udf UserFormFieldEditorExtension
     * @return array
     */
    public function validate($data, $udf) : array
    {
        $ret = false;
        $message = null;

        if ($this->isInvalidEmail($data, $udf)) {
            $ret = true;
            $message = 'Failed in the email checks.';
        }

        if ($this->hasInvalidStrings($data, $udf)) {
            $ret = true;
            $message = 'Contains invalid characters or strings.';
        }

        if ($this->isInvalidIPAddress($data, $udf)) {
            $ret = true;
            $message = 'From an invalid IP';
        }

        if ($this->isThrottled()) {
            $ret = true;
            $message = 'Throttled';
        }

        return [$ret, $message];
    }

    /**
     * @param $data array
     * @param $udf UserFormFieldEditorExtension
     * @return bool
     */
    private function isInvalidEmail($data, $udf) : bool
    {
        /* @var $form SubmittedForm */
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedEmailAddresses
            && ($emailsPatterns = $this->getComparisons($config->BlockedEmailAddresses))
            && ($emailFields = $udf->Fields()->filter('ClassName', EditableEmailField::class))
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

    /**
     * @param $data array
     * @param $udf UserFormFieldEditorExtension
     * @return bool
     */
    private function hasInvalidStrings($data, $udf) : bool
    {
        /* @var $form SubmittedForm */
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedKeywords
            && ($patterns = $this->getComparisons($config->BlockedKeywords));
        if ($check) {
            foreach ($patterns as $pattern) {
                foreach ($data as $name => $value) {
                    if ($this->matchWildcard($pattern, $value)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param $data array
     * @param $udf UserFormFieldEditorExtension
     * @return bool
     */
    private function isInvalidIPAddress($data, $udf) : bool
    {
        if ($ip = $this->getIpAddress()) {
            $config = SiteConfig::current_site_config();
            $check = $config->BlockedIPAddresses
                && ($ipAddresses = $this->getComparisons($config->BlockedIPAddresses));
            if ($check) {
                foreach ($ipAddresses as $ipAddress) {
                    if ($ip == $ipAddress) {
                        return true;
                    }
                }
            }
        }
        return false;
    }


    private function isThrottled() : bool
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

    private function getIpAddress() : ?string
    {
        if (Controller::has_curr()) {
            $controller = Controller::curr();
            return $controller->getRequest()->getIP();
        }
        return null;
    }

}
