<?php

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Stores valid email(s) as per configuration.
 *
 * Usage:
 *  $user->addField('email', [Field\Email::class]);
 *  $user->addField('email_mx_check', [Field\Email::class, 'dns_check'=>true]);
 *  $user->addField('email_with_name', [Field\Email::class, 'include_names'=>true]);
 *  $user->addField('emails', [Field\Email::class, 'allow_multiple'=>true, 'separator'=>[',',';']]);
 *
 * Various options can also be combined.
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

    /**
     * @var array Array of allowed separators
     */
    public $separator = [','];

    /**
     * Perform normalization.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        // split value by any number of separator characters
        $emails = preg_split('/[' . implode('', array_map('preg_quote', $this->separator)) . ']+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (!$this->allow_multiple && count($emails) > 1) {
            throw new ValidationException([$this->name => 'Only a single email can be entered']);
        }

        // now normalize each email
        $emails = array_map(function ($email) {
            $email = trim($email);

            if ($this->include_names) {
                $email = preg_replace('/^[^<]*<([^>]*)>/', '\1', $email);
            }

            // should actually run only domain trough idn_to_ascii(), but for validation purpose this way it's fine too
            $p = explode('@', $email, 2);
            $user = $p[0] ?? null;
            $domain = $p[1] ?? null;
            if (!$domain) {
                throw new ValidationException([$this->name => 'Email address does not have domain']);
            }

            $domain = idn_to_ascii($domain); // always convert domain to ASCII

            if (!filter_var($user . '@' . $domain, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException([$this->name => 'Email address format is invalid']);
            }

            if ($this->dns_check) {
                if (!$this->isDNSValid($domain)) {
                    throw new ValidationException([$this->name => 'Email address domain does not exist']);
                }
            }

            return $email;
        }, $emails);

        return parent::normalize(implode(', ', $emails));
    }

    /**
     * Validate DNS.
     */
    protected function isDNSValid(string $domain): bool
    {
        return $this->hasDNSRecord($domain, true) || $this->hasDNSRecord($domain, false);
    }

    private function hasDNSRecord(string $domain, bool $isMX): bool
    {
        $normalizedDomain = $domain . '.';
        if (!checkdnsrr($normalizedDomain, ($isMX ? 'MX' : 'A'))) {
            return false;
        }

        $records = dns_get_record($normalizedDomain, ($isMX ? DNS_MX : DNS_A));

        return $records !== false && count($records) > 0;
    }
}
