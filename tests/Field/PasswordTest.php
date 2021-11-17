<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Field;

use Atk4\Data\Field\Password;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class PasswordTest extends TestCase
{
    public function testPasswordField(): void
    {
        $m = new Model();
        $m->addField('p', [Password::class]);
        $passwordField = Password::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        $passwordField->setPassword($entity, 'mypass');
        $this->assertNotSame('mypass', $entity->get('p'));
        $this->assertFalse($passwordField->verifyPassword($entity, 'badpass'));
        $this->assertTrue($passwordField->verifyPassword($entity, 'mypass'));
        $this->assertFalse($passwordField->verifyPassword($entity, 'mypass' . ' '));
    }

    public function testSetUnhashedException(): void
    {
        $m = new Model();
        $m->addField('p', [Password::class]);
        $passwordField = Password::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        $this->expectException(\Atk4\Data\Exception::class);
        $passwordField->set($entity, 'mypass');
    }

    public function testEmptyCompareException(): void
    {
        $m = new Model();
        $m->addField('p', [Password::class]);
        $passwordField = Password::assertInstanceOf($m->getField('p'));
        $entity = $m->createEntity();

        $this->expectException(\Atk4\Data\Exception::class);
        $passwordField->verifyPassword($entity, 'mypass');
    }

    public function testGeneratePassword(): void
    {
        $field = new Password();

        $pwd = $field->generatePassword();
        $this->assertIsString($pwd);
        $this->assertSame(8, strlen($pwd));

        $pwd = $field->generatePassword(100);
        $this->assertIsString($pwd);
        $this->assertSame(100, strlen($pwd));
    }
}
