<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;

class PasswordField extends Field
{
    /** @var int */
    public $minLength = 8;

    public function normalizePassword(string $password, bool $forVerifyOnly): string
    {
        $password = (new Field(['type' => 'string']))->normalize($password);

        if (!preg_match('~^\P{C}+$~u', $password) || $this->hashPasswordIsHashed($password)) {
            throw new Exception('Invalid password');
        } elseif (!$forVerifyOnly && mb_strlen($password) < $this->minLength) {
            throw new Exception('At least ' . $this->minLength . ' characters are required');
        }

        return $password;
    }

    public function hashPassword(string $password): string
    {
        $password = $this->normalizePassword($password, false);

        $hash = \password_hash($password, \PASSWORD_BCRYPT, ['cost' => 8]);
        $e = false;
        try {
            if (!$this->hashPasswordIsHashed($hash) || !$this->hashPasswordVerify($hash, $password)) {
                $e = null;
            }
        } catch (\Exception $e) {
        }
        if ($e !== false) {
            throw new Exception('Unexpected error when hashing password', 0, $e);
        }

        return $hash;
    }

    public function hashPasswordVerify(string $hash, string $password): bool
    {
        $hash = $this->normalize($hash);
        $password = $this->normalizePassword($password, true);

        return \password_verify($password, $hash);
    }

    public function hashPasswordIsHashed(string $value): bool
    {
        try {
            $value = parent::normalize($value) ?? '';
        } catch (\Exception $e) {
        }

        return \password_get_info($value)['algo'] === \PASSWORD_BCRYPT;
    }

    public function normalize($hash): ?string
    {
        $hash = parent::normalize($hash);

        if ($hash !== null && ($hash === '' || !$this->hashPasswordIsHashed($hash))) {
            throw new Exception('Invalid password hash');
        }

        return $hash;
    }

    public function setPassword(Model $entity, string $password): self
    {
        $this->set($entity, $this->hashPassword($password));

        return $this;
    }

    /**
     * Returns true if the supplied password matches the stored hash.
     */
    public function verifyPassword(Model $entity, string $password): bool
    {
        $v = $this->get($entity);
        if ($v === null) {
            throw (new Exception('Password hash is null, verification is impossible'))
                ->addMoreInfo('field', $this->shortName);
        }

        return $this->hashPasswordVerify($v, $password);
    }

    public function generatePassword(int $length = null): string
    {
        $charsAll = array_diff(array_merge(
            range('0', '9'),
            range('a', 'z'),
            range('A', 'Z'),
        ), ['0', 'o', 'O', '1', 'l', 'i', 'I']);

        $resArr = [];
        for ($i = 0; $i < max(8, $length ?? $this->minLength); ++$i) {
            $chars = array_values(array_diff($charsAll, array_slice($resArr, -4)));
            $resArr[] = $chars[random_int(0, count($chars) - 1)];
        }

        return $this->normalizePassword(implode('', $resArr), false);
    }
}
