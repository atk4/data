<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Field;

use Atk4\Data\Exception;
use Atk4\Data\Field\PasswordField;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class PasswordFieldTest extends TestCase
{
    public function testPasswordFieldBasic(): void
    {
        $m = new Model();
        $m->addField('p', [PasswordField::class]);
        $field = PasswordField::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        static::assertNull($entity->get('p'));

        $field->setPassword($entity, 'myPassword');
        static::assertIsString($entity->get('p'));
        static::assertNotSame('myPassword', $entity->get('p'));
        static::assertFalse($field->verifyPassword($entity, 'badPassword'));
        static::assertTrue($field->verifyPassword($entity, 'myPassword'));

        // password is always normalized using string type
        static::assertTrue($field->verifyPassword($entity, 'myPassword '));
        static::assertFalse($field->verifyPassword($entity, 'myPassword .'));

        $field->set($entity, null);
        static::assertNull($entity->get('p'));
    }

    public function testInvalidPasswordAlreadyHashed(): void
    {
        $field = new PasswordField();
        $hash = $field->hashPassword('myPassword');

        $this->expectException(Exception::class);
        $field->hashPassword($hash);
    }

    public function testInvalidPasswordTooShortDefault(): void
    {
        $field = new PasswordField();
        $pwd = 'žlutý__';
        static::assertTrue(mb_strlen($pwd) < $field->minLength);
        static::assertTrue(strlen($pwd) >= $field->minLength);

        $this->expectException(Exception::class);
        $field->hashPassword($pwd);
    }

    public function testInvalidPasswordTooShortCustomized(): void
    {
        $field = new PasswordField();
        $pwd = 'myPassword';
        static::assertFalse($field->hashPasswordIsHashed($pwd));
        $hash = $field->hashPassword($pwd);
        static::assertTrue($field->hashPasswordIsHashed($hash));

        $field->minLength = 50;

        // minLength is ignored for verify
        static::assertTrue($field->hashPasswordVerify($hash, $pwd . ' '));

        // but checked when password is being hashed
        $this->expectException(Exception::class);
        $field->hashPassword(str_repeat('x', 49));
    }

    public function testInvalidPasswordCntrlChar(): void
    {
        $field = new PasswordField();
        $pwd = 'myPassword' . "\t" . 'x';
        $hash = $field->hashPassword($pwd);
        static::assertTrue($field->hashPasswordIsHashed($hash));
        static::assertTrue($field->hashPasswordVerify($hash, str_replace("\t", ' ', $pwd)));

        $this->expectException(Exception::class);
        $field->hashPassword('myPassword' . "\x07" . 'x');
    }

    public function testSetUnhashedException(): void
    {
        $m = new Model();
        $m->addField('p', [PasswordField::class]);
        $field = PasswordField::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        $this->expectException(Exception::class);
        $field->set($entity, 'myPassword');
    }

    public function testEmptyCompareException(): void
    {
        $m = new Model();
        $m->addField('p', [PasswordField::class]);
        $field = PasswordField::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        $this->expectException(Exception::class);
        $field->verifyPassword($entity, 'myPassword');
    }

    public function testGeneratePassword(): void
    {
        $field = new PasswordField();

        $pwd = $field->generatePassword();
        static::assertSame(8, strlen($pwd));

        $pwd = $field->generatePassword(50);
        static::assertSame(50, strlen($pwd));
    }
}
