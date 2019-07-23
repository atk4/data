<?php


namespace atk4\data\Field;


use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Stores valid email(s) as per configuration
 *
 * @package atk4\data\Field
 */
class Email extends Field
{

    /**
     * @var bool Enable lookup for MX record for email addresses stored
     */
    public $dns_check = false;

    /**
     * @var bool Permit entry of multiple email addresses, separated with comma (and extra spaces)
     */
    public $allow_multiple = false;

    /**
     * @var bool Also allow entry of names in format "Romans <me@example.com>"
     */
    public $include_names = false;

    function normalize($value)
    {

        // use comma as separator
        $emails = explode(',', $value);

        if (!$this->allow_multiple && count($emails)>1) {
            throw new Exception(['Only a single email can be entered', 'email'=>$value]);
        }

        array_map(function($email){

            $email = trim($email);

            if ($this->include_names) {
                $email = preg_replace('/^[^<]*<([^>]*)>/', '\1', $email);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(['Email format is invalid', 'email'=>$email]);
            }

            $domain = explode('@', $email)[1];

            if ($this->dns_check && !checkdnsrr($domain, 'MX')) {
                throw new Exception(['Email domain does not exist', 'domain'=>$domain]);
            }
        }, $emails);

        return parent::normalize(join(', ', array_map('trim', $emails)));
    }
}