<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Field;

use Atk4\Data\Field\EmailField;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\ValidationException;

class EmailFieldTest extends TestCase
{
    public function testEmailFieldBasic(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class]);
        $entity = $m->createEntity();

        self::assertNull($entity->get('email'));

        // normal value
        $entity->set('email', 'foo@example.com');
        self::assertSame('foo@example.com', $entity->get('email'));

        // null value
        $entity->set('email', null);
        self::assertNull($entity->get('email'));

        // padding, spacing etc removed
        $entity->set('email', " \t " . 'foo@example.com ' . " \n ");
        self::assertSame('foo@example.com', $entity->get('email'));

        // no domain - go to hell :)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not have domain');
        $entity->set('email', 'xx');
    }

    public function testEmailValidateDns(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class, 'dnsCheck' => true]);
        $entity = $m->createEntity();

        $entity->set('email', ' foo@gmail.com');
        self::assertSame('foo@gmail.com', $entity->get('email'));

        $entity->set('email', ' foo@mail.co.uk');
        self::assertSame('foo@mail.co.uk', $entity->get('email'));

        $entity->set('email', 'test@háčkyčárky.cz'); // official IDN test domain
        self::assertSame('test@háčkyčárky.cz', $entity->get('email'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('domain does not exist');
        $entity->set('email', 'test@háčkyčárky2.cz');
    }

    public function testEmailWithName(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class]);
        $m->addField('email_name', [EmailField::class, 'allowName' => true]);
        $entity = $m->createEntity();

        $entity->set('email_name', 'Žlutý Kůň <me3@❤.com>');
        self::assertSame('Žlutý Kůň <me3@❤.com>', $entity->get('email_name'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $entity->set('email', 'Romans <me@gmail.com>');
    }

    public function testEmailMultipleException(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class]);
        $entity = $m->createEntity();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $entity->set('email', 'foo@exampe.com, bar@example.com');
    }
}
