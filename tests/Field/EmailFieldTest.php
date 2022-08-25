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

        static::assertNull($entity->get('email'));

        // normal value
        $entity->set('email', 'foo@example.com');
        static::assertSame('foo@example.com', $entity->get('email'));

        // null value
        $entity->set('email', null);
        static::assertNull($entity->get('email'));

        // padding, spacing etc removed
        $entity->set('email', " \t " . 'foo@example.com ' . " \n ");
        static::assertSame('foo@example.com', $entity->get('email'));

        // no domain - go to hell :)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not have domain');
        $entity->set('email', 'xx');
    }

    public function testEmailValidateDns(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class, 'dnsCheck' => true]);
        $m->addField('email_idn', [EmailField::class, 'dnsCheck' => true]);
        $entity = $m->createEntity();

        $entity->set('email', ' foo@gmail.com');

        $entity->set('email_idn', 'test@háčkyčárky.cz'); // official IDN test domain
        static::assertSame('test@háčkyčárky.cz', $entity->get('email_idn'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('domain does not exist');
        $entity->set('email', ' foo@lrcanoetuhasnotdusantotehusontehuasntddaontehudnouhtd.com');
    }

    public function testEmailWithName(): void
    {
        $m = new Model();
        $m->addField('email', [EmailField::class]);
        $m->addField('email_name', [EmailField::class, 'allowName' => true]);
        $entity = $m->createEntity();

        $entity->set('email_name', 'Žlutý Kůň <me3@❤.com>');
        static::assertSame('Žlutý Kůň <me3@❤.com>', $entity->get('email_name'));

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
