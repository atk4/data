<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Field\Callback;
use atk4\data\Field\Integer;

/**
 * Test various Field.
 */
class FieldTypesTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public $pers = null;

    public function setUp()
    {
        parent::setUp();

        $this->pers = new \atk4\data\Persistence\Static_([
            1 => ['name'=>'John'],
            2 => ['name'=>'Peter'],
        ]);
    }

    public function testBoolean1()
    {
        $m = new Model($this->pers);
        $m->addField('is_vip_1', ['type'=>'boolean', 'required'=>true]);
        $m->addField('is_vip_2', ['Boolean', 'required'=>true]);
        $m->addField('is_vip_3', ['Boolean', 'default'=>false, 'required'=>true]);

        //$this->expectException(ValidationException::class);
        $m->save(); // this should throw validation exception but normalize() is not called at all in this case !!!

        /*
        $this->expectException(ValidationException::class);
        $m->save(['is_vip_1'=>false]);

        $this->expectException(ValidationException::class);
        $m->save(['is_vip_2'=>false]);
        */
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

    /**
     * @group dns
     */
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
    
    public function testCallback()
    {
        $model = new Model($this->pers);
        $model->addField('callback', [Callback::class, 'fx'=>function($model) {
            return $model['name'];
        }]);
        
        $model->each(function($model) {
            $this->assertEquals($model['callback'], $model['name']);
        });
    }
    
    public function testInteger()
    {
        $model = new Model($this->pers);
        $model->addField('integer', [Integer::class]);
        
        $model['integer'] = 55.55;
        
        $model->save();

        $this->assertEquals($model['integer'], 55);
    }
}
