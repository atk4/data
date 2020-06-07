<?php

namespace atk4\data\tests;

use atk4\data\Field;
use atk4\data\Model;
use atk4\data\ValidationException;
use atk4\data\Persistence\Static_ as Persistence_Static;

/**
 * Test various Field.
 */
class FieldTypesTest extends \atk4\schema\PhpunitTestCase
{
    public $pers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name' => 'John'],
            2 => ['name' => 'Peter'],
        ]);
    }

    public function testEmail1()
    {
        $m = new Model($this->pers);
        $m->addField('email', Field\Email::class);

        $m->set('email', ' foo@example.com');
        $m->save();

        // padding removed
        $this->assertSame('foo@example.com', $m->get('email'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $m->set('email', 'qq');
    }

    public function testEmail2()
    {
        $m = new Model($this->pers);
        $m->addField('email', [Field\Email::class]);
        $m->addField('emails', [Field\Email::class, 'allow_multiple' => true]);

        $m->set('emails', 'bar@exampe.com ,foo@example.com');
        $this->assertSame('bar@exampe.com, foo@example.com', $m->get('emails'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('a single email');
        $m->set('email', 'bar@exampe.com ,foo@example.com');
    }

    public function testEmail3()
    {
        $m = new Model($this->pers);
        $m->addField('email', [Field\Email::class, 'dns_check' => true]);

        $m->set('email', ' foo@gmail.com');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not exist');
        $m->set('email', ' foo@lrcanoetuhasnotdusantotehusontehuasntddaontehudnouhtd.com');
    }

    public function testEmail4()
    {
        $m = new Model($this->pers);
        $m->addField('email_name', [Field\Email::class, 'include_names' => true]);
        $m->addField('email_names', [Field\Email::class, 'include_names' => true, 'allow_multiple' => true, 'dns_check' => true, 'separator' => [',', ';']]);
        $m->addField('email_idn', [Field\Email::class, 'dns_check' => true]);
        $m->addField('email', [Field\Email::class]);

        $m->set('email_name', 'Romans <me@gmail.com>');
        $m->set('email_names', 'Romans1 <me1@gmail.com>, Romans2 <me2@gmail.com>; Romans3 <me3@gmail.com>');
        $m->set('email_idn', 'test@日本レジストリサービス.jp');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('format is invalid');
        $m->set('email', 'Romans <me@gmail.com>');
    }
}
