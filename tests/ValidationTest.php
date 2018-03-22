<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_Array;

class MyValidationModel extends Model
{
    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('domain');
    }

    public function validate($intent = null)
    {
        $errors = [];
        if ($this['name'] === 'Python') {
            $errors['name'] = 'Snakes are not allowed on this plane';
        }
        if ($this['domain'] === 'example.com') {
            $errors['domain'] = 'This domain is reserved for examples only';
        }

        return array_merge(parent::validate(), $errors);
    }
}

class BadValidationModel extends Model
{
    public function init()
    {
        parent::init();

        $this->addField('name');
    }

    public function validate($intent = null)
    {
        return 'This should be array';
    }
}

class ValidationTests extends \atk4\core\PHPUnit_AgileTestCase
{
    public $m;

    public function setUp()
    {
        $a = [];
        $p = new Persistence_Array($a);
        $this->m = new MyValidationModel($p);
    }

    public function testValidate1()
    {
        $this->m['name'] = 'john';
        $this->m['domain'] = 'gmail.com';
        $this->m->save();
    }

    /**
     * @expectedException        \atk4\data\ValidationException
     * @expectedExceptionMessage Snakes
     */
    public function testValidate2()
    {
        $this->m['name'] = 'Python';
        $this->m->save();
    }

    /**
     * @expectedException        \atk4\data\ValidationException
     * @expectedExceptionMessage Multiple
     */
    public function testValidate3()
    {
        $this->m['name'] = 'Python';
        $this->m['domain'] = 'example.com';
        $this->m->save();
    }

    public function testValidate4()
    {
        try {
            $this->m['name'] = 'Python';
            $this->m['domain'] = 'example.com';
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertEquals('This domain is reserved for examples only', $e->getParams()['errors']['domain']);

            return;
        }
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage Incorrect use of ValidationException, argument should be an array
     */
    public function testValidate5()
    {
        $a = [];
        $p = new Persistence_Array($a);
        $m = new BadValidationModel($p);

        $m['name'] = 'john';
        $m->save();
    }

    public function testValidateHook()
    {
        $this->m->addHook('validate', function ($m) {
            if ($m['name'] === 'C#') {
                return ['name'=>'No sharp objects allowed'];
            }
        });

        $this->m['name'] = 'Swift';
        $this->m->save();

        try {
            $this->m['name'] = 'C#';
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertEquals('No sharp objects allowed', $e->errors['name']);
        }

        try {
            $this->m['name'] = 'Python';
            $this->m['domain'] = 'example.com';
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertEquals(2, count($e->errors));
        }
    }
}
