<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;

class ConditionTest extends TestCase
{
    public function testUnexistingFieldException(): void
    {
        $m = new Model();
        $m->addField('name');

        $this->expectException(Exception::class);
        $m->addCondition('last_name', 'Smith');
    }

    public function testBasicDiscrimination(): void
    {
        $m = new Model();
        $m->addField('name');

        $m->addField('gender', ['enum' => ['M', 'F']]);
        $m->addField('foo');

        $m->addCondition('gender', 'M');

        $this->assertCount(1, $m->scope()->getNestedConditions());

        $m->addCondition('gender', 'F');

        $this->assertCount(2, $m->scope()->getNestedConditions());
    }

    public function testEditableAfterCondition(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', 'M');

        $this->assertTrue($m->getField('gender')->system);
        $this->assertFalse($m->getField('gender')->isEditable());
    }

    public function testEditableHasOne(): void
    {
        $gender = new Model();
        $gender->addField('name');

        $m = new Model();
        $m->addField('name');
        $m->hasOne('gender_id', ['model' => $gender]);

        $this->assertFalse($m->getField('gender_id')->system);
        $this->assertTrue($m->getField('gender_id')->isEditable());
    }
}
