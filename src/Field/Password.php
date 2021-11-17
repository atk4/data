<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;

class Password extends Field
{
    public function hashPassword(string $password): string
    {
        return password_hash($password, \PASSWORD_BCRYPT, ['cost' => 8]);
    }

    public function hashPasswordVerify(string $hash, string $password): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @internal valid password can be a valid password hash, never use to this method
     *           to distinguish if a password is already hashed
     */
    protected function hashPasswordIsHashed(string $value): bool
    {
        $info = password_get_info($value);

        return $info['algo'] !== null;
    }

    public function normalize($value)
    {
        $value = parent::normalize($value);
        if ($value !== null && ($value === '' || !$this->hashPasswordIsHashed($value))) {
            throw new Exception('Invalid password hash');
        }

        return $value;
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
                ->addMoreInfo('field', $this->name);
        }

        return $this->hashPasswordVerify($v, $password);
    }

    public function generatePassword(int $length = 8): string
    {
        $charsAll = array_diff(array_merge(
            range('0', '9'),
            range('a', 'z'),
            range('A', 'Z'),
        ), ['0', 'o', 'O', '1', 'l', 'i', 'I']);

        $resArr = [];
        for ($i = 0; $i < $length; ++$i) {
            $chars = array_values(array_diff($charsAll, array_slice($resArr, -4)));
            $resArr[] = $chars[random_int(0, count($chars) - 1)];
        }

        return implode('', $resArr);
    }
}
