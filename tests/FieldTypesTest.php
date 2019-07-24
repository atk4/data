<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_Static;
use atk4\data\ValidationException;

/**
 * Test various Field.
 */
class FieldTypesTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public $pers = null;

    public function setUp()
    {
        parent::setUp();

        $this->pers = new Persistence_Static([
            1 => ['name'=>'John'],
            2 => ['name'=>'Peter'],
        ]);
    }

    public function testBoolean1()
    {
        $m = new Model($this->pers);
        $m->addField('is_vip_1', ['type'=>'boolean', 'required'=>true]);
        $m->addField('is_vip_2', ['Boolean', 'required'=>true]);

        //$this->expectException(ValidationException::class);
        $m->save(); // this should throw validation exception but normalize() is not called at all in this case !!!

        $this->expectException(ValidationException::class);
        $m->save(['is_vip_1'=>false]);

        $this->expectException(ValidationException::class);
        $m->save(['is_vip_2'=>false]);
    }

    public function testEmail1()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['Email']);

        $m['email'] = ' foo@example.com';
        $m->save();

        // padding removed
        $this->assertEquals('foo@example.com', $m['email']);

        $this->expectExceptionMessage('format is invalid');
        $m['email'] = 'qq';
    }

    public function testEmail2()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['Email']);
        $m->addField('emails', ['Email', 'allow_multiple'=>true]);

        $m['emails'] = 'bar@exampe.com ,foo@example.com';
        $this->assertEquals('bar@exampe.com, foo@example.com', $m['emails']);

        $this->expectExceptionMessage('a single email');
        $m['email'] = 'bar@exampe.com ,foo@example.com';
    }

    public function testEmail3()
    {
        $m = new Model($this->pers);
        $m->addField('email', ['Email', 'dns_check'=>true]);

        $m['email'] = ' foo@gmail.com';

        $this->expectExceptionMessage('does not exist');
        $m['email'] = ' foo@lrcanoetuhasnotdusantotehusontehuasntddaontehudnouhtd.com';
    }

    public function testEmail4()
    {
        $m = new Model($this->pers);
        $m->addField('email_name', ['Email', 'include_names'=>true]);
        $m->addField('email_names', ['Email', 'include_names'=>true, 'allow_multiple'=>true, 'dns_check'=>true, 'separator'=>[',', ';']]);
        $m->addField('email_idn', ['Email', 'dns_check'=>true]);
        $m->addField('email', ['Email']);

        $m['email_name'] = 'Romans <me@gmail.com>';
        $m['email_names'] = 'Romans1 <me1@gmail.com>, Romans2 <me2@gmail.com>; Romans3 <me3@gmail.com>';
        $m['email_idn'] = 'test@日本レジストリサービス.jp';

        $this->expectExceptionMessage('format is invalid');
        $m['email'] = 'Romans <me@gmail.com>';
    }
}
