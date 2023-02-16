<?php

namespace SilverStripers\UDFSpamProtection\Utils;

use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\Form;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * @property Form $form
 */
class FormSpamUtils extends SpamUtils
{
    /**
     * @var $form Form
     */
    private $form = null;

    public function setForm(Form $form)
    {
        $this->form = $form;
        return $this;
    }

    private function emailFields()
    {
        $fields = $this->form->Fields()->dataFields();
        $emailFields = [];
        foreach ($fields as $field) {
            if (is_a($field, EmailField::class)) {
                $emailFields[] = $field;
            }
        }
        return $emailFields;
    }

    protected function isInvalidEmail($data) : bool
    {
        $config = SiteConfig::current_site_config();
        $check = $config->BlockedEmailAddresses
            && ($emailsPatterns = $this->getComparisons($config->BlockedEmailAddresses))
            && ($emailFields = $this->emailFields())
            && count($emailFields);
        if ($check) {
            foreach ($emailsPatterns as $emailsPattern) {
                /* @var $emailField EmailField */
                foreach ($emailFields as $emailField) {
                    if (!empty($data[$emailField->getName()])) {
                        $value = $data[$emailField->getName()];
                        if ($value && $this->matchWildcard($emailsPattern, $value)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}
