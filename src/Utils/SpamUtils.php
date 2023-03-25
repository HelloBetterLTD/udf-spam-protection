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

    public static function inst() : static
    {
        $inst = Injector::inst()->get(get_called_class());
        return $inst;
    }

    /**
     * @param $data
     * @param $udf UserFormFieldEditorExtension
     * @return array
     */
    public function validate($data) : array
    {
        $ret = false;
        $message = null;

        if ($this->isInvalidEmail($data)) {
            $ret = true;
            $message = 'Failed in the email checks.';
        }

        if ($this->hasInvalidStrings($data)) {
            $ret = true;
            $message = 'Contains invalid characters or strings.';
        }

        if ($this->isInvalidIPAddress($data)) {
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
    protected function isInvalidEmail($data) : bool
    {
        return false;
    }

    /**
     * @param $data array
     * @return bool
     */
    protected function hasInvalidStrings($data) : bool
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
     * @return bool
     */
    protected function isInvalidIPAddress($data) : bool
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


    protected function isThrottled() : bool
    {
        return false;
    }


    protected function getComparisons($str) : array
    {
        $lines = explode(PHP_EOL, $str);
        return array_filter(array_map('trim', $lines));
    }

    protected function matchWildcard($pattern, $string) : bool
    {
        $pattern = '#^' . $this->wildcardToRegex($pattern). '$#iu';
        return (bool) preg_grep($pattern, is_array($string) ? $string : [$string]);
    }

    protected function wildcardToRegex($pattern, $delimiter = '/') : string
    {
        $converted = preg_quote($pattern, $delimiter);
        $converted = str_replace('\*', '.*', $converted);
        return $converted;
    }

    protected function getIpAddress() : ?string
    {
        if (Controller::has_curr()) {
            $controller = Controller::curr();
            return $controller->getRequest()->getIP();
        }
        return null;
    }

}
