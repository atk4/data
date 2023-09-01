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

        self::assertNull($m->get('nodefault'));
        self::assertSame('abc', $m->get('withdefault'));
    }

    public function testDirty1(): void
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);
        $m = $m->createEntity();

        self::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        self::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'bca');
        self::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'abc');
        self::assertFalse($m->isDirty('foo'));

        // set initial data
        $m->getDataRef()['foo'] = 'xx';
        self::assertFalse($m->isDirty('foo'));

        $m->set('foo', 'abc');
        self::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'bca');
        self::assertTrue($m->isDirty('foo'));

        $m->set('foo', 'xx');
        self::assertFalse($m->isDirty('foo'));
    }

    public function testCompare(): void
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);
        $m = $m->createEntity();

        self::assertTrue($m->compare('foo', 'abc'));
        $m->set('foo', 'zzz');

        self::assertFalse($m->compare('foo', 'abc'));
        self::assertTrue($m->compare('foo', 'zzz'));
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
        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                ['id' => 2, 'name' => 'NoName', 'surname' => 'qq'],
            ],
        ], $this->getDb());
    }

    public function testCaption(): void
    {
        $m = new Model();

        $m->addField('foo');
        self::assertSame(
            'Foo',
            $m->getField('foo')->getCaption()
        );

        $m->addField('user_defined_entity');
        self::assertSame(
            'User Defined Entity',
            $m->getField('user_defined_entity')->getCaption()
        );

        $m->addField('foo2', ['caption' => 'My Foo']);
        self::assertSame(
            'My Foo',
            $m->getField('foo2')->getCaption()
        );

        $m->addField('foo3', ['ui' => ['caption' => 'My Foo']]);
        self::assertSame(
            'My Foo',
            $m->getField('foo3')->getCaption()
        );

        $m->addField('userDefinedEntity');
        self::assertSame(
            'User Defined Entity',
            $m->getField('userDefinedEntity')->getCaption()
        );

        $m->addField('newNASA_module');
        self::assertSame(
            'New NASA Module',
            $m->getField('newNASA_module')->getCaption()
        );

        $m->addField('this\\ _isNASA_MyBigBull shit_123\Foo');
        self::assertSame(
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
        self::assertSame('abc', $m->get('foo'));
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

        self::assertSame('1', $m->get('foo'));

        $m->set('foo', 'bar');
        self::assertSame('bar', $m->get('foo'));
    }

    public function testEnum2b(): void
    {
        $m = new Model();
        $m->addField('foo', ['type' => 'integer', 'enum' => [1, 2]]);
        $m = $m->createEntity();
        $m->set('foo', 1);

        self::assertSame(1, $m->get('foo'));

        $m->set('foo', '2');
        self::assertSame(2, $m->get('foo'));
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

        self::assertNull($m->get('foo'));
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

        self::assertSame(3, $m->get('foo'));

        $m->set('foo', null);
        self::assertNull($m->get('foo'));
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
        self::assertSame('1a', $m->get('foo'));
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

        self::assertNull($m->get('name'));
        self::assertSame('Smith', $m->get('surname'));

        $m->set('name', 'Bill');
        $m->set('surname', 'Stalker');
        $m->save();
        self::assertSame($dbData, $this->getDb());

        $m->reload();
        self::assertSame('Smith', $m->get('surname'));
        $m->getField('surname')->neverSave = false;
        $m->set('surname', 'Stalker');
        $m->save();
        $dbData['item'][1]['surname'] = 'Stalker';
        self::assertSame($dbData, $this->getDb());

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

        self::assertSame($dbData, $this->getDb());
        self::assertNull($m->get('name'));
        self::assertSame('X', $m->get('surname'));

        $m->set('surname', 'Y');
        $m->save();

        self::assertSame($dbData, $this->getDb());
        self::assertSame('Y', $m->get('name'));
        self::assertSame('X', $m->get('surname'));
    }

    public function testTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                ['id' => 2, 'name' => 'Programmer'],
                ['id' => 3, 'name' => 'Sales'],
            ],
        ]);

        $c = new Model($this->db, ['table' => 'category']);
        $c->addField('name');

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->hasOne('category_id', ['model' => $c])
            ->addTitle();

        $m = $m->load(1);

        self::assertSame('John', $m->get('name'));
        self::assertSame('Programmer', $m->get('category'));

        $m->getModel()->insert(['name' => 'Peter', 'category' => 'Sales']);

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
                ['id' => 2, 'name' => 'Peter', 'surname' => null, 'category_id' => 3],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                ['id' => 2, 'name' => 'Programmer'],
                ['id' => 3, 'name' => 'Sales'],
            ],
        ], $this->getDb());
    }

    public function testNonExistingField(): void
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
        self::assertSame('John', $mm->get('first_name'));

        self::assertSameExportUnordered([
            ['id' => 1, 'first_name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'first_name' => 'Peter', 'surname' => 'qq'],
        ], $m->export());

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
                ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
            ],
        ], $this->getDb());

        $mm->set('first_name', 'Scott');
        $mm->save();

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'Scott', 'surname' => 'Smith'],
                ['id' => 2, 'name' => 'Peter', 'surname' => 'qq'],
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
        $m->addCalculatedField('total', ['expr' => static function (Model $m) {
            return $m->get('net') + $m->get('vat');
        }, 'type' => 'atk4_money']);
        $m->insert(['net' => 30, 'vat' => 8]);

        $mm = $m->load(1);
        self::assertSame(121.0, $mm->get('total'));
        $mm = $m->load(2);
        self::assertSame(38.0, $mm->get('total'));

        $d = $m->export(); // in export calculated fields are not included
        self::assertFalse(array_key_exists('total', $d[0]));
    }

    public function testSystem1(): void
    {
        $m = new Model();
        $m->addField('foo', ['system' => true]);
        $m->addField('bar');
        self::assertFalse($m->getField('foo')->isEditable());
        self::assertFalse($m->getField('foo')->isVisible());

        $m->setOnlyFields(['bar']);
        // TODO: build a query and see if the field is there
    }

    public function testNormalize(): void
    {
        // normalize must work even without model
        self::assertSame('test', (new Field(['type' => 'string']))->normalize('test'));
        self::assertSame('test', (new Field(['type' => 'string']))->normalize('test '));

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
        self::assertSame('Two Lines', $m->get('string'));

        $m->set('string', "Two\rLines  ");
        self::assertSame('Two Lines', $m->get('string'));

        $m->set('string', "Two\nLines  ");
        self::assertSame('Two Lines', $m->get('string'));

        // text
        $m->set('text', "Two\r\nLines  ");
        self::assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\rLines  ");
        self::assertSame("Two\nLines", $m->get('text'));

        $m->set('text', "Two\nLines  ");
        self::assertSame("Two\nLines", $m->get('text'));

        // integer, money, float
        $m->set('integer', '12,345.67676767');
        self::assertSame(12345, $m->get('integer'));

        $m->set('money', '12,345.67676767');
        self::assertSame(12345.6768, $m->get('money'));

        $m->set('float', '12,345.67676767');
        self::assertSame(12345.67676767, $m->get('float'));

        // boolean
        $m->set('boolean', 0);
        self::assertFalse($m->get('boolean'));
        $m->set('boolean', 1);
        self::assertTrue($m->get('boolean'));
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

        self::assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));
        self::assertSame(['system', 'editable_system', 'visible_system'], array_keys($model->getFields('system')));
        self::assertSame(['editable', 'visible', 'not_editable'], array_keys($model->getFields('not system')));
        self::assertSame(['editable', 'editable_system', 'visible'], array_keys($model->getFields('editable')));
        self::assertSame(['editable', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields('visible')));
        self::assertSame(['editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields(['editable', 'visible'])));

        $model->getModel()->setOnlyFields(['system', 'visible', 'not_editable']);

        // getFields() is unaffected by onlyFields, will always return all fields
        self::assertSame(['system', 'editable', 'editable_system', 'visible', 'visible_system', 'not_editable'], array_keys($model->getFields()));

        // only return subset of onlyFields
        self::assertSame(['visible', 'not_editable'], array_keys($model->getFields('visible')));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Field filter is not supported');
        $model->getFields('foo');
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
        self::assertSame('x', $m->get('a'));
        self::assertSame('y', $m->get('b'));
        self::assertSame('z', $m->get('c'));
        $m->set('a', '');
        $m->set('b', '');
        self::assertSame('', $m->get('a'));
        self::assertSame('', $m->get('b'));
        $m->set('a', null);
        self::assertNull($m->get('a'));

        // null must pass
        $m->setNull('a');
        $m->setNull('b');
        $m->getField('c')->setNull($m);
        self::assertNull($m->get('a'));
        self::assertNull($m->get('b'));
        self::assertNull($m->get('c'));

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

        self::assertSame($m, $entityFooField->getModel());
        self::assertSame($m, $entityBarField->getModel());
        self::assertSame($entity, $entityFooField->getEntity());
        self::assertSame($entity, $entityBarField->getEntity());
        self::assertSame('foo', $entityFooField->getFieldName());
        self::assertSame('bar', $entityBarField->getFieldName());
        self::assertSame($m->getField('foo'), $entityFooField->getField());
        self::assertSame($m->getField('bar'), $entityBarField->getField());

        self::assertNull($entityFooField->get());
        self::assertNull($entityBarField->get());

        $entity->set('foo', 'a');
        self::assertSame('a', $entityFooField->get());
        $entityBarField->set('b');
        self::assertSame('b', $entityBarField->get());
        $entityBarField->setNull();
        self::assertNull($entityBarField->get());

        $this->expectException(Exception::class);
        $entityBarField->set(null);
    }

    public function testBoolean(): void
    {
        $m = new Model();
        $m->addField('is_vip', ['type' => 'boolean']);
        $m = $m->createEntity();

        $m->set('is_vip', false);
        self::assertFalse($m->get('is_vip'));
        $m->set('is_vip', true);
        self::assertTrue($m->get('is_vip'));
        $m->set('is_vip', 0);
        self::assertFalse($m->get('is_vip'));
        $m->set('is_vip', 1);
        self::assertTrue($m->get('is_vip'));
    }
}
