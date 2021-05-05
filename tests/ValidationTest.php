<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\ValidationException;

class MyValidationModel extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('domain');
    }

    public function validate($intent = null): array
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
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
    }

    public function validate($intent = null): array
    {
        return 'This should be array'; // @phpstan-ignore-line
    }
}

class ValidationTest extends AtkPhpunit\TestCase
{
    /** @var Model */
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $p = new Persistence\Array_();
        $this->m = new MyValidationModel($p);
    }

    public function testValidate1(): void
    {
        $m = $this->m->createEntity();
        $m->set('name', 'john');
        $m->set('domain', 'gmail.com');
        $m->save();
        $this->assertTrue(true); // no exception
    }

    public function testValidate2(): void
    {
        $m = $this->m->createEntity();
        $m->set('name', 'Python');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Snakes');
        $m->save();
    }

    public function testValidate3(): void
    {
        $m = $this->m->createEntity();
        $m->set('name', 'Python');
        $m->set('domain', 'example.com');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Multiple');
        $m->save();
    }

    public function testValidate4(): void
    {
        $m = $this->m->createEntity();
        try {
            $m->set('name', 'Python');
            $m->set('domain', 'example.com');
            $m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertSame('This domain is reserved for examples only', $e->getParams()['errors']['domain']);

            return;
        }
    }

    public function testValidate5(): void
    {
        $p = new Persistence\Array_();
        $m = new BadValidationModel($p);
        $m = $m->createEntity();

        $this->expectException(\TypeError::class);
        $m->set('name', 'john');
        $m->save();
    }

    public function testValidateHook(): void
    {
        $m = $this->m->createEntity();

        $m->onHook(Model::HOOK_VALIDATE, static function ($m) {
            if ($m->get('name') === 'C#') {
                return ['name' => 'No sharp objects allowed'];
            }
        });

        $m->set('name', 'Swift');
        $m->save();

        try {
            $m->set('name', 'C#');
            $m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertSame('No sharp objects allowed', $e->errors['name']);
        }

        try {
            $m->set('name', 'Python');
            $m->set('domain', 'example.com');
            $m->save();
            $this->fail('Expected exception');
        } catch (\Atk4\Data\ValidationException $e) {
            $this->assertCount(2, $e->errors);
        }
    }
}
