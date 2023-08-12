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

        self::assertNull($entity->get('p'));

        $field->setPassword($entity, 'myPassword');
        self::assertIsString($entity->get('p'));
        self::assertNotSame('myPassword', $entity->get('p'));
        self::assertFalse($field->verifyPassword($entity, 'badPassword'));
        self::assertTrue($field->verifyPassword($entity, 'myPassword'));

        // password is always normalized using string type
        self::assertTrue($field->verifyPassword($entity, 'myPassword '));
        self::assertFalse($field->verifyPassword($entity, 'myPassword .'));

        $field->set($entity, null);
        self::assertNull($entity->get('p'));
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
        self::assertTrue(mb_strlen($pwd) < $field->minLength);
        self::assertTrue(strlen($pwd) >= $field->minLength);

        $this->expectException(Exception::class);
        $field->hashPassword($pwd);
    }

    public function testInvalidPasswordTooShortCustomized(): void
    {
        $field = new PasswordField();
        $pwd = 'myPassword';
        self::assertFalse($field->hashPasswordIsHashed($pwd));
        $hash = $field->hashPassword($pwd);
        self::assertTrue($field->hashPasswordIsHashed($hash));

        $field->minLength = 50;

        // minLength is ignored for verify
        self::assertTrue($field->hashPasswordVerify($hash, $pwd . ' '));

        // but checked when password is being hashed
        $this->expectException(Exception::class);
        $field->hashPassword(str_repeat('x', 49));
    }

    public function testInvalidPasswordCntrlChar(): void
    {
        $field = new PasswordField();
        $pwd = 'myPassword' . "\t" . 'x';
        $hash = $field->hashPassword($pwd);
        self::assertTrue($field->hashPasswordIsHashed($hash));
        self::assertTrue($field->hashPasswordVerify($hash, str_replace("\t", ' ', $pwd)));

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
        self::assertSame(8, strlen($pwd));

        $pwd = $field->generatePassword(50);
        self::assertSame(50, strlen($pwd));
    }
}
