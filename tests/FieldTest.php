<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\ValidationException;

class FieldTest extends TestCase
{
    public function testDefaultValue(): void
    {
        $m = new Model();
        $m->addField('nodefault');
        $m->addField('withdefault', ['default' => 'abc']);
        $m = $m->createEntity();

        static::assertNull($m->get('nodefault'));
        static::assertSame('abc', $m->get('withdefault'));
    }

    public function testDirty1(): void
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);
        $m = $m->createEntity();

        static::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        static::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'bca');
        static::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'abc');
        static::assertFalse($m->isDirty('foo'));

        // set initial data
        $m->getDataRef()['foo'] = 'xx';
        static::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        static::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'bca');
        static::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'xx');
        static::assertFalse($m->isDirty('foo'));
    }

    public function testCompare(): void
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);
        $m = $m->createEntity();

        static::assertTrue($m->compare('foo', 'abc'));
        $m->set('foo', 'zzz');

        static::assertFalse($m->compare('foo', 'abc'));
        static::assertTrue($m->compare('foo', 'zzz'));
    }

    public function testNotNullable1(): void
    {
        $m = new Model();
        $m->addField('foo', ['nullable' => false]);
        $m = $m->createEntity();
        $m->set('foo', 'abc');
        $m->set('foo', '');

        $this->expectException(ValidationException::class);
        $m->set('foo', null);
    }

    public function testRequired1(): void
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', '');
    }

    public function testRequired11(): void
    {
        $m = new Model();
        $m->addField('foo', ['required' => true]);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', null);
    }

    public function testNotNullable2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name', ['nullable' => false]);
        $m->addField('surname');

        $this->expectException(Exception::class);
        $m->insert(['surname' => 'qq']);
    }

    public function testRequired2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name', ['required' => true]);
        $m->addField('surname');

        $this->expectException(Exception::class);
        $m->insert(['surname' => 'qq', 'name' => '']);
    }

    public function testNotNullable3(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name', ['nullable' => false]);
        $m->addField('surname');
        $m = $m->load(1);

        $this->expectException(Exception::class);
        $m->save(['name' => null]);
    }

    public function testNotNullable4(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name', ['nullable' => false, 'default' => 'NoName']);
        $m->addField('surname');
        $m->insert(['surname' => 'qq']);
        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'NoName', 'surname' => 'qq'],
            ],
        ], $this->getDb());
    }

    public function testCaption(): void
    {
        $m = new Model();

        $m->addField('foo');
        static::assertSame(
            'Foo',
            $m->getField('foo')->getCaption()
        );

        $m->addField('user_defined_entity');
        static::assertSame(
            'User Defined Entity',
            $m->getField('user_defined_entity')->getCaption()
        );

        $m->addField('foo2', ['caption' => 'My Foo']);
        static::assertSame(
            'My Foo',
            $m->getField('foo2')->getCaption()
        );

        $m->addField('foo3', ['ui' => ['caption' => 'My Foo']]);
        static::assertSame(
            'My Foo',
            $m->getField('foo3')->getCaption()
        );

        $m->addField('userDefinedEntity');
        static::assertSame(
            'User Defined Entity',
            $m->getField('userDefinedEntity')->getCaption()
        );

        $m->addField('newNASA_module');
        static::assertSame(
            'New NASA Module',
            $m->getField('newNASA_module')->getCaption()
        );

        $m->addField('this\\ _isNASA_MyBigBull shit_123\Foo');
        static::assertSame(
            'This Is NASA My Big Bull Shit 123 Foo',
            $m->getField('this\\ _isNASA_MyBigBull shit_123\Foo')->getCaption()
        );
    }

    public function testReadOnly1(): void
    {
        $m = new Model();
        $m->addField('foo', ['readOnly' => true]);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('foo', 'bar');
    }

    public function testReadOnly2(): void
    {
        $m = new Model();
        $m->addField('foo', ['readOnly' => true, 'default' => 'abc']);
        $m = $m->createEntity();
        $m->set('foo', 'abc');
        static::assertSame('abc', $m->get('foo'));
    }

    public function testEnum1(): void
    {
        $m = new Model();
        $m->addField('foo', ['enum' => ['foo', 'bar']]);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('foo', 'xx');
    }

    public function testEnum2(): void
    {
        $m = new Model();
        $m->addField('foo', ['enum' => ['1', 'bar']]);
        $m = $m->createEntity();
        $m->set('foo', '1');

        static::assertSame('1', $m->get('foo'));

        $m->set('foo', 'bar');
        static::assertSame('bar', $m->get('foo'));
    }

    public function testEnum2b(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'integer', 'enum' => [1, 2]]);
        $m = $m->createEntity();
        $m->set('foo', 1);

        static::assertSame(1, $m->get('foo'));

        $m->set('foo', '2');
        static::assertSame(2, $m->get('foo'));
    }

    public function testEnum3(): void
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar']]);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('foo', true);
    }

    public function testEnum4(): void
    {
        $m = new Model();
        $m->addField('foo', ['enum' => [1, 'bar'], 'default' => 1]);
        $m = $m->createEntity();
        $m->setNull('foo');

        static::assertNull($m->get('foo'));
    }

    public function testValues1(): void
    {
        $m = new Model();
        $m->addField('foo', ['values' => ['foo', 'bar']]);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('foo', 4);
    }

    public function testValues2(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'integer', 'values' => [3 => 'bar']]);
        $m = $m->createEntity();
        $m->set('foo', 3);

        static::assertSame(3, $m->get('foo'));

        $m->set('foo', null);
        static::assertNull($m->get('foo'));
    }

    public function testValues3(): void
    {
        $m = new Model();
        $m->addField('foo', ['values' => [1 => 'bar']]);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('foo', 'bar');
    }

    public function testValues4(): void
    {
        // PHP type control is really crappy...
        // This test has no purpose but it stands testament
        // to a weird behaviours of PHP
        $m = new Model();
        $m->addField('foo', ['values' => ['1a' => 'bar']]);
        $m = $m->createEntity();
        $m->set('foo', '1a');
        static::assertSame('1a', $m->get('foo'));
    }

    public function testNeverPersist(): void
    {
        $this->setDb($dbData = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name', ['neverPersist' => true]);
        $m->addField('surname', ['neverSave' => true]);
        $m = $m->load(1);

        static::assertNull($m->get('name'));
        static::assertSame('Smith', $m->get('surname'));

        $m->set('name', 'Bill');
        $m->set('surname', 'Stalker');
        $m->save();
        static::assertSame($dbData, $this->getDb());

        $m->reload();
        static::assertSame('Smith', $m->get('surname'));
        $m->getField('surname')->neverSave = false;
        $m->set('surname', 'Stalker');
        $m->save();
        $dbData['item'][1]['surname'] = 'Stalker';
        static::assertSame($dbData, $this->getDb());

        $m->onHook(Model::HOOK_BEFORE_SAVE, static function (Model $m) {
            if ($m->isDirty('name')) {
                $m->set('surname', $m->get('name'));
                $m->_unset('name');
            } elseif ($m->isDirty('surname')) {
                $m->set('name', $m->get('surname'));
                $m->_unset('surname');
            }
        });

        $m->set('name', 'X');
        $m->save();

        $dbData['item'][1]['surname'] = 'X';

        static::assertSame($dbData, $this->getDb());
        static::assertNull($m->get('name'));
        static::assertSame('X', $m->get('surname'));

        $m->set('surname', 'Y');
        $m->save();

        static::assertSame($dbData, $this->getDb());
        static::assertSame('Y', $m->get('name'));
        static::assertSame('X', $m->get('surname'));
    }

    public function testTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ]);

        $c = new Model($this->db, ['table' => 'category']);
        $c->addField('name');

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->hasOne('category_id', ['model' => $c])
            ->addTitle();

        $m = $m->load(1);

        static::assertSame('John', $m->get('name'));
        static::assertSame('Programmer', $m->get('category'));

        $m->getModel()->insert(['name' => 'Peter', 'category' => 'Sales']);

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => null, 'category_id' => 3],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ], $this->getDb());
    }

    public function testNonExisitngField(): void
    {
        $m = new Model();
        $m->addField('foo');
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('baz', 'bar');
    }

    public function testActual(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('first_name', ['actual' => 'name']);
        $m->addField('surname');
        $m->insert(['first_name' => 'Peter', 'surname' => 'qq']);

        $mm = $m->loadBy('first_name', 'John');
        static::assertSame('John', $mm->get('first_name'));

        static::assertSameExportUnordered([
            ['id' => 1, 'first_name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'first_name' => 'Peter', 'surname' => 'qq'],
        ], $m->export());

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ],
        ], $this->getDb());

        $mm->set('first_name', 'Scott');
        $mm->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'Scott', 'surname' => 'Smith'],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ],
        ], $this->getDb());
    }

    public function testCalculatedField(): void
    {
        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'net' => 100, 'vat' => 21],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'invoice']);
        $m->addField('net', ['type' => 'atk4_money']);
        $m->addField('vat', ['type' => 'atk4_money']);
        $m->addCalculatedField('total', ['expr' => function (Model $m) {
            return $m->get('net') + $m->get('vat');
        }, 'type' => 'atk4_money']);
        $m->insert(['net' => 30, 'vat' => 8]);

        $mm = $m->load(1);
        static::assertSame(121.0, $mm->get('total'));
        $mm = $m->load(2);
        static::assertSame(38.0, $mm->get('total'));

        $d = $m->export(); // in export calculated fields are not included
        static::assertFalse(array_key_exists('total', $d[0]));
    }

    public function testSystem1(): void
    {
        $m = new Model();
        $m->addField('foo', ['system' => true]);
        $m->addField('bar');
        static::assertFalse($m->getField('foo')->isEditable());
        static::assertFalse($m->getField('foo')->isVisible());

        $m->setOnlyFields(['bar']);
        // TODO: build a query and see if the field is there
    }

    public function testNormalize(): void
    {
        // normalize must work even without model
        static::assertSame('test', (new Field(['type' => 'string']))->normalize('test'));
        static::assertSame('test', (new Field(['type' => 'string']))->normalize('test '));

        $m = new Model();

        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('json', ['type' => 'json']);
        $m->addField('object', ['type' => 'object']);
        $m = $m->createEntity();

        // string
        $m->set('string', "Two\r\nLines  ");
        static::assertSame('Two Lines', $m->get('string'));

        $m->set('string', "Two\rLines  ");
        static::assertSame('Two Lines', $m->get('string'));

        $m->set('string', "Two\nLines  ");
        static::assertSame('Two Lines', $m->get('string'));

        // text
        $m->set('text', "Two\r\nLines  ");
        static::assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\rLines  ");
        static::assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\nLines  ");
        static::assertSame("Two\nLines", $m->get('text'));

        // integer, money, float
        $m->set('integer', '12,345.67676767');
        static::assertSame(12345, $m->get('integer'));

        $m->set('money', '12,345.67676767');
        static::assertSame(12345.6768, $m->get('money'));

        $m->set('float', '12,345.67676767');
        static::assertSame(12345.67676767, $m->get('float'));

        // boolean
        $m->set('boolean', 0);
        static::assertFalse($m->get('boolean'));
        $m->set('boolean', 1);
        static::assertTrue($m->get('boolean'));
    }

    public function testNormalizeException1(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'string']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException2(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'text']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException3(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'integer']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException4(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'atk4_money']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException5(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'float']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException6(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'date']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException7(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'datetime']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException8(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'time']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', []);
    }

    public function testNormalizeException9(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'integer']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException10(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'atk4_money']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException11(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'float']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', '123---456');
    }

    public function testNormalizeException12(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'json']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testNormalizeException13(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'object']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testNormalizeException14(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'boolean']);
        $m = $m->createEntity();

        $this->expectException(ValidationException::class);
        $m->set('foo', 'ABC');
    }

    public function testToString(): void
    {
        $m = new Model();

        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('json', ['type' => 'json']);
        $m->addField('object', ['type' => 'object']);
        $m = $m->createEntity();

        static::assertSame('Two Lines', $m->getField('string')->toString("Two\r\nLines  "));
        static::assertSame("Two\nLines", $m->getField('text')->toString("Two\r\nLines  "));
        static::assertSame('123', $m->getField('integer')->toString(123));
        static::assertSame('123.45', $m->getField('money')->toString(123.45));
        static::assertSame('123.456789', $m->getField('float')->toString(123.456789));
        static::assertSame('1', $m->getField('boolean')->toString(true));
        static::assertSame('0', $m->getField('boolean')->toString(false));
        static::assertSame('2019-01-20', $m->getField('date')->toString(new \DateTime('2019-01-20T12:23:34 UTC')));
        static::assertSame('2019-01-20 12:23:34.000000', $m->getField('datetime')->toString(new \DateTime('2019-01-20 12:23:34 UTC')));
        static::assertSame('12:23:34.000000', $m->getField('time')->toString(new \DateTime('2019-01-20 12:23:34 UTC')));
        static::assertSame('{"foo":"bar","int":123,"rows":["a","b"]}', $m->getField('json')->toString(['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']]));
        static::assertSame('O:8:"stdClass":3:{s:3:"foo";s:3:"bar";s:3:"int";i:123;s:4:"rows";a:2:{i:0;s:1:"a";i:1;s:1:"b";}}', $m->getField('object')->toString((object) ['foo' => 'bar', 'int' => 123, 'rows' => ['a', 'b']]));
    }

    public function testAddFieldDirectly(): void
    {
        $model = new Model();

        $this->expectException(Exception::class);
        $model->add(new Field());
    }

    public function testGetFields(): void
    {
        $model = new Model();
        $model->addField('system', ['system' => true]);
        $model->addField('editable', ['ui' => ['editable' => true]]);
        $model->addField('editable_system', ['ui' => ['editable' => true], 'system' => true]);
        $model->addField('visible', ['ui' => ['visible' => true]]);
        $model->addField('visible_system', ['ui' => ['visible' => true], 'system' => true]);
        $model->addField('not_editable', ['ui' => ['editable' => false]]);
        $model = $model->createEntity();

        static::assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));
        static::assertSame(['system', 'editable_system', 'visible_system'], array_keys($model->getFields('system')));
        static::assertSame(['editable', 'visible', 'not_editable'], array_keys($model->getFields('not system')));
        static::assertSame(['editable', 'editable_system', 'visible'], array_keys($model->getFields('editable')));
        static::assertSame(['editable', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields('visible')));
        static::assertSame(['editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields(['editable', 'visible'])));

        $model->getModel()->setOnlyFields(['system', 'visible', 'not_editable']);

        // getFields() is unaffected by onlyFields, will always return all fields
        static::assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));

        // only return subset of onlyFields
        static::assertSame(['visible', 'not_editable'], array_keys($model->getFields('visible')));

        $this->expectExceptionMessage('not supported');
        $model->getFields('foo');
    }

    public function testDateTimeFieldsToString(): void
    {
        $model = new Model();
        $model->addField('date', ['type' => 'date']);
        $model->addField('time', ['type' => 'time']);
        $model->addField('datetime', ['type' => 'datetime']);
        $model = $model->createEntity();

        static::assertSame('', $model->getField('date')->toString($model->get('date')));
        static::assertSame('', $model->getField('time')->toString($model->get('time')));
        static::assertSame('', $model->getField('datetime')->toString($model->get('datetime')));

        // datetime without microseconds
        $dt = new \DateTime('2020-01-21 21:09:42 UTC');
        $model->set('date', $dt);
        $model->set('time', $dt);
        $model->set('datetime', $dt);

        static::assertSame($dt->format('Y-m-d'), $model->getField('date')->toString($model->get('date')));
        static::assertSame($dt->format('H:i:s.u'), $model->getField('time')->toString($model->get('time')));
        static::assertSame($dt->format('Y-m-d H:i:s.u'), $model->getField('datetime')->toString($model->get('datetime')));

        // datetime with microseconds
        $dt = new \DateTime('2020-01-21 21:09:42.895623 UTC');
        $model->set('date', $dt);
        $model->set('time', $dt);
        $model->set('datetime', $dt);

        static::assertSame($dt->format('Y-m-d'), $model->getField('date')->toString($model->get('date')));
        static::assertSame($dt->format('H:i:s.u'), $model->getField('time')->toString($model->get('time')));
        static::assertSame($dt->format('Y-m-d H:i:s.u'), $model->getField('datetime')->toString($model->get('datetime')));
    }

    public function testSetNull(): void
    {
        $m = new Model();
        $m->addField('a');
        $m->addField('b', ['nullable' => false]);
        $m->addField('c', ['required' => true]);
        $m = $m->createEntity();

        // valid value for set()
        $m->set('a', 'x');
        $m->set('b', 'y');
        $m->set('c', 'z');
        static::assertSame('x', $m->get('a'));
        static::assertSame('y', $m->get('b'));
        static::assertSame('z', $m->get('c'));
        $m->set('a', '');
        $m->set('b', '');
        static::assertSame('', $m->get('a'));
        static::assertSame('', $m->get('b'));
        $m->set('a', null);
        static::assertNull($m->get('a'));

        // null must pass
        $m->setNull('a');
        $m->setNull('b');
        $m->getField('c')->setNull($m);
        static::assertNull($m->get('a'));
        static::assertNull($m->get('b'));
        static::assertNull($m->get('c'));

        $this->expectException(Exception::class);
        $m->set('c', null);
    }

    public function testEntityFieldPair(): void
    {
        $m = new Model();
        $m->addField('foo');
        $m->addField('bar', ['nullable' => false]);

        $entity = $m->createEntity();
        $entityFooField = new Model\EntityFieldPair($entity, 'foo');
        $entityBarField = new Model\EntityFieldPair($entity, 'bar');

        static::assertSame($m, $entityFooField->getModel());
        static::assertSame($m, $entityBarField->getModel());
        static::assertSame($entity, $entityFooField->getEntity());
        static::assertSame($entity, $entityBarField->getEntity());
        static::assertSame('foo', $entityFooField->getFieldName());
        static::assertSame('bar', $entityBarField->getFieldName());
        static::assertSame($m->getField('foo'), $entityFooField->getField());
        static::assertSame($m->getField('bar'), $entityBarField->getField());

        static::assertNull($entityFooField->get());
        static::assertNull($entityBarField->get());

        $entity->set('foo', 'a');
        static::assertSame('a', $entityFooField->get());
        $entityBarField->set('b');
        static::assertSame('b', $entityBarField->get());
        $entityBarField->setNull();
        static::assertNull($entityBarField->get());

        $this->expectException(Exception::class);
        $entityBarField->set(null);
    }

    public function testBoolean(): void
    {
        $m = new Model();
        $m->addField('is_vip', ['type' => 'boolean']);
        $m = $m->createEntity();

        $m->set('is_vip', false);
        static::assertFalse($m->get('is_vip'));
        $m->set('is_vip', true);
        static::assertTrue($m->get('is_vip'));
        $m->set('is_vip', 0);
        static::assertFalse($m->get('is_vip'));
        $m->set('is_vip', 1);
        static::assertTrue($m->get('is_vip'));
    }
}
