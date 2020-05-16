<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;

class MyValidationModel extends Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('domain');
    }

    public function validate($intent = null)
    {
        $errors = [];
        if ($this->get('name') === 'Python') {
            $errors['name'] = 'Snakes are not allowed on this plane';
        }
        if ($this->get('domain') === 'example.com') {
            $errors['domain'] = 'This domain is reserved for examples only';
        }

        return array_merge(parent::validate(), $errors);
    }
}

class BadValidationModel extends Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('name');
    }

    public function validate($intent = null)
    {
        return 'This should be array';
    }
}

class ValidationTests extends AtkPhpunit\TestCase
{
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $a = [];
        $p = new Persistence\Array_($a);
        $this->m = new MyValidationModel($p);
    }

    public function testValidate1()
    {
        $this->m->set('name', 'john');
        $this->m->set('domain', 'gmail.com');
        $this->m->save();
    }

    /**
     * @expectedException        \atk4\data\ValidationException
     * @expectedExceptionMessage Snakes
     */
    public function testValidate2()
    {
        $this->m->set('name', 'Python');
        $this->m->save();
    }

    /**
     * @expectedException        \atk4\data\ValidationException
     * @expectedExceptionMessage Multiple
     */
    public function testValidate3()
    {
        $this->m->set('name', 'Python');
        $this->m->set('domain', 'example.com');
        $this->m->save();
    }

    public function testValidate4()
    {
        try {
            $this->m->set('name', 'Python');
            $this->m->set('domain', 'example.com');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertSame('This domain is reserved for examples only', $e->getParams()['errors']['domain']);

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
        $p = new Persistence\Array_($a);
        $m = new BadValidationModel($p);

        $m->set('name', 'john');
        $m->save();
    }

    public function testValidateHook()
    {
        $this->m->onHook('validate', function ($m) {
            if ($m->get('name') === 'C#') {
                return ['name' => 'No sharp objects allowed'];
            }
        });

        $this->m->set('name', 'Swift');
        $this->m->save();

        try {
            $this->m->set('name', 'C#');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertSame('No sharp objects allowed', $e->errors['name']);
        }

        try {
            $this->m->set('name', 'Python');
            $this->m->set('domain', 'example.com');
            $this->m->save();
            $this->fail('Expected exception');
        } catch (\atk4\data\ValidationException $e) {
            $this->assertSame(2, count($e->errors));
        }
    }
}
