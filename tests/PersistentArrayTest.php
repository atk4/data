<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Female as Female;
use atk4\data\tests\Model\Male as Male;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentArrayTest extends AtkPhpunit\TestCase
{
    /**
     * Test constructor.
     */
    public function testLoadArray()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->unload();
        $this->assertFalse($m->loaded());

        $m->tryLoadAny();
        $this->assertTrue($m->loaded());

        $m->load(2);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Smith', $m['surname']);
    }

    public function testSaveAs()
    {
        //$this->markTestSkipped('TODO: see #146');
        $a = [
            'person' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ];

        $p = new Persistence\Array_($a);

        $m = new Male($p);
        $m->load(1);
        $m->saveAs(new Female());
        $m->delete();

        $this->assertEquals([
            'person' => [
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
                3 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F', 'id'=>3],
            ],
        ], $a);
    }

    public function testSaveAndUnload()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ];

        $p = new Persistence\Array_($a);
        $m = new Male($p, 'user');

        $m->load(1);
        $this->assertTrue($m->loaded());
        $m['gender'] = 'F';
        $m->saveAndUnload();
        $this->assertFalse($m->loaded());

        $m = new Female($p, 'user');
        $m->load(1);
        $this->assertTrue($m->loaded());

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $a);
    }

    public function testUpdateArray()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $m['name'] = 'Peter';
        $m->save();

        $m->load(2);
        $m['surname'] = 'Smith';
        $m->save();
        $m['surname'] = 'QQ';
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $a);

        $m->unload();
        $m->set(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'id' => 3],
            ],
        ], $a);
    }

    public function testInsert()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'id' => 3],
            ],
        ], $a);
    }

    public function testIterator()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row['name'];
        }

        $this->assertEquals('JohnSarah', $output);
    }

    /**
     * Test short format.
     */
    public function testShortFormat()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Smith', $m['surname']);
    }

    /**
     * Some persistences don't support tryLoad() method.
     *
     * @expectedException Exception
     */
    public function testTryLoadNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->tryLoad(1);
    }

    /**
     * Some persistences don't support loadAny() method.
     *
     * @expectedException Exception
     */
    public function testLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->loadAny();
    }

    /**
     * Some persistences don't support tryLoadAny() method.
     *
     * @expectedException Exception
     */
    public function testTryLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->tryLoadAny();
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals([
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertEquals([
            1 => ['surname' => 'Smith'],
            2 => ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }

    /**
     * Test Model->action('count').
     */
    public function testActionCount()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals(2, $m->action('count')->getOne());
    }

    /**
     * Test Model->action('field').
     */
    public function testActionField()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals(2, $m->action('count')->getOne());

        // use alias as array key if it is set
        $q = $m->action('field', ['name', 'alias'=>'first_name']);
        $this->assertEquals(['first_name'=>'John'], $q);

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['name']);
        $this->assertEquals(['name'=>'John'], $q);
    }

    /**
     * Test Model->addCondition operator LIKE.
     */
    public function testLike()
    {
        $a = ['countries' => [
            1 => ['id'=>1, 'name'=>'ABC9', 'code'=>11, 'country'=>'Ireland', 'active'=>1],
            2 => ['id'=>2, 'name'=>'ABC8', 'code'=>12, 'country'=>'Ireland', 'active'=>0],
            3 => ['id'=>3, 'code'=>13, 'country'=>'Latvia', 'active'=>1],
            4 => ['id'=>4, 'name'=>'ABC6', 'code'=>14, 'country'=>'UK', 'active'=>0],
            5 => ['id'=>5, 'name'=>'ABC5', 'code'=>15, 'country'=>'UK', 'active'=>0],
            6 => ['id'=>6, 'name'=>'ABC4', 'code'=>16, 'country'=>'Ireland', 'active'=>1],
            7 => ['id'=>7, 'name'=>'ABC3', 'code'=>17, 'country'=>'Latvia', 'active'=>0],
            8 => ['id'=>8, 'name'=>'ABC2', 'code'=>18, 'country'=>'Russia', 'active'=>1],
            9 => ['id'=>9, 'code'=>19, 'country'=>'Latvia', 'active'=>1],
        ]];

        $p = new Persistence\Array_($a);
        $m = new Model($p, 'countries');
        $m->addField('code', ['type' => 'int']);
        $m->addField('country');
        $m->addField('active', ['type' => 'boolean']);

        // if no condition we should get all the data back
        $iterator = $m->action('select');
        $result = $m->persistence->applyConditions($m, $iterator);
        $this->assertInstanceOf(\atk4\data\Action\Iterator::class, $result);
        $m->unload();
        unset($iterator);
        unset($result);

        // case : str%
        $m->addCondition('country', 'LIKE', 'La%');
        $result = $m->action('select')->get();
        $this->assertEquals(3, count($result));
        $this->assertEquals($a['countries'][3], $result[3]);
        $this->assertEquals($a['countries'][7], $result[7]);
        $this->assertEquals($a['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : %str
        $m->conditions = [];
        $m->addCondition('country', 'LIKE', '%ia');
        $result = $m->action('select')->get();
        $this->assertEquals(4, count($result));
        $this->assertEquals($a['countries'][3], $result[3]);
        $this->assertEquals($a['countries'][7], $result[7]);
        $this->assertEquals($a['countries'][8], $result[8]);
        $this->assertEquals($a['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : %str%
        $m->conditions = [];
        $m->addCondition('country', 'LIKE', '%a%');
        $result = $m->action('select')->get();
        $this->assertEquals(7, count($result));
        $this->assertEquals($a['countries'][1], $result[1]);
        $this->assertEquals($a['countries'][2], $result[2]);
        $this->assertEquals($a['countries'][3], $result[3]);
        $this->assertEquals($a['countries'][6], $result[6]);
        $this->assertEquals($a['countries'][7], $result[7]);
        $this->assertEquals($a['countries'][8], $result[8]);
        $this->assertEquals($a['countries'][9], $result[9]);
        unset($result);
        $m->unload();

        // case : boolean field
        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '0');
        $this->assertEquals(4, count($m->export()));

        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '1');
        $this->assertEquals(5, count($m->export()));

        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '%0%');
        $this->assertEquals(4, count($m->export()));

        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '%1%');
        $this->assertEquals(5, count($m->export()));

        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '%999%');
        $this->assertEquals(0, count($m->export()));

        $m->conditions = [];
        $m->addCondition('active', 'LIKE', '%ABC%');
        $this->assertEquals(0, count($m->export()));
    }

    /**
     * Returns exported data, but will use get() instead of export().
     *
     * @param \atk4\data\Model $m
     * @param array            $fields
     *
     * @return array
     */
    protected function _getRows(\atk4\data\Model $m, $fields = [])
    {
        $d = [];
        foreach ($m as $junk) {
            $row = $m->get();
            $row = $fields ? array_intersect_key($row, array_flip($fields)) : $row;
            $d[] = $row;
        }

        return $d;
    }

    /**
     * Test Model->setOrder().
     */
    public function testOrder()
    {
        $a = [
            ['id' => 1, 'f1' => 'A', 'f2' => 'B'],
            ['id' => 2, 'f1' => 'D', 'f2' => 'A'],
            ['id' => 3, 'f1' => 'D', 'f2' => 'C'],
            ['id' => 4, 'f1' => 'A', 'f2' => 'C'],
            ['id' => 5, 'f1' => 'E', 'f2' => 'A'],
            ['id' => 6, 'f1' => 'C', 'f2' => 'A'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1');
        $d = $this->_getRows($m, ['f1']);
        $this->assertEquals([
            ['f1'=>'A'],
            ['f1'=> 'A'],
            ['f1'=> 'C'],
            ['f1'=> 'D'],
            ['f1'=> 'D'],
            ['f1'=> 'E'],
        ], $d);
        $this->assertEquals($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by one field descending
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');
        $m->setOrder('f1', true);
        $d = $this->_getRows($m, ['f1']);
        $this->assertEquals([
            ['f1'=>'E'],
            ['f1'=> 'D'],
            ['f1'=> 'D'],
            ['f1'=> 'C'],
            ['f1'=> 'A'],
            ['f1'=> 'A'],
        ], $d);
        $this->assertEquals($d, array_values($m->export(['f1']))); // array_values to get rid of keys

        // order by two fields ascending
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('f1');
        $m->addField('f2');
        $m->addField('f3');

        $m->setOrder('f1', true);
        $m->setOrder('f2', true);
        $d = $this->_getRows($m, ['f1', 'f2', 'id']);
        $this->assertEquals([
            ['f1'=>'E', 'f2'=>'A', 'id'=>5],
            ['f1'=> 'D', 'f2'=>'C', 'id'=>3],
            ['f1'=> 'D', 'f2'=>'A', 'id'=>2],
            ['f1'=> 'C', 'f2'=>'A', 'id'=>6],
            ['f1'=> 'A', 'f2'=>'C', 'id'=>4],
            ['f1'=> 'A', 'f2'=>'B', 'id'=>1],
        ], $d);
        $this->assertEquals($d, array_values($m->export(['f1', 'f2', 'id']))); // array_values to get rid of keys
    }

    /**
     * Test Model->setLimit().
     */
    public function testLimit()
    {
        $a = [
            ['f1' => 'A'],
            ['f1' => 'D'],
            ['f1' => 'E'],
            ['f1' => 'C'],
        ];

        // order by one field ascending
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('f1');

        $this->assertEquals(4, $m->action('count')->getOne());

        $m->setLimit(3);
        $this->assertEquals(3, $m->action('count')->getOne());
        $this->assertEquals([
            ['f1' => 'A'],
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], array_values($m->export()));

        $m->setLimit(2, 1);
        $this->assertEquals(2, $m->action('count')->getOne());
        $this->assertEquals([
            ['f1' => 'D'],
            ['f1' => 'E'],
        ], array_values($m->export()));

        // well, this is strange, that you can actually change limit on-the-fly and then previous
        // limit is not taken into account, but most likely you will never set it multiple times
        $m->setLimit(3);
        $this->assertEquals(3, $m->action('count')->getOne());
    }

    /**
     * Test Model->addCondition().
     */
    public function testCondition()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            3 => ['name' => 'Sarah', 'surname' => 'XX'],
            4 => ['name' => 'Sarah', 'surname' => 'Smith'],
        ];

        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals(4, $m->action('count')->getOne());
        $this->assertEquals($a['data'], $m->export());

        $m->addCondition('name', 'Sarah');
        $this->assertEquals(3, $m->action('count')->getOne());

        $m->addCondition('surname', 'Smith');
        $this->assertEquals(1, $m->action('count')->getOne());
        $this->assertEquals([4=>['name'=>'Sarah', 'surname'=>'Smith']], $m->export());
        $this->assertEquals([4=>['name'=>'Sarah', 'surname'=>'Smith']], $m->action('select')->get());

        $m->addCondition('surname', 'Siiiith');
        $this->assertEquals(0, $m->action('count')->getOne());
    }

    /**
     * @expectedException Exception
     */
    public function testUnsupportedAction()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->action('foo');
    }

    /**
     * @expectedException Exception
     */
    public function testBadActionArgs()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->action('select', 'foo'); // args should be array
    }

    /**
     * @expectedException Exception
     */
    public function testUnsupportedCondition1()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addCondition('name');
        $m->export();
    }

    /**
     * unsupported method.
     *
     * @expectedException Exception
     */
    public function testUnsupportedCondition2()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addCondition('name', '<>', 'John');
        $m->export();
    }

    /**
     * unsupported format - 4th param.
     *
     * @expectedException Exception
     */
    public function testUnsupportedCondition3()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addCondition('name', 'like', '%o%', 'CASE_INSENSITIVE');
        $m->export();
    }

    /**
     * unsupported format - param[0] not Field::class.
     *
     * @expectedException Exception
     */
    public function testUnsupportedCondition5()
    {
        $a = [1=>['name'=>'John']];
        $p = new Persistence\Array_($a);
        $m = new Model($p);
        $m->addField('name');
        $m->addCondition(new Model(), 'like', '%o%');
        $m->export();
    }

    /**
     * Test Model->hasOne().
     */
    public function testHasOne()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id'=>1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id'=>2],
            ],
            'country'=> [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ];

        $p = new Persistence\Array_($a);

        $user = new Model($p, 'user');
        $user->addField('name');
        $user->addField('surname');

        $country = new Model();
        $country->table = 'country';
        $country->addField('name');

        $user->hasOne('country_id', $country);

        $user->load(1);
        $this->assertEquals('Latvia', $user->ref('country_id')['name']);

        $user->load(2);
        $this->assertEquals('UK', $user->ref('country_id')['name']);
    }

    /**
     * Test Model->hasMany().
     */
    public function testHasMany()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'country_id'=>1],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'country_id'=>2],
                3 => ['name' => 'Janis', 'surname' => 'Berzins', 'country_id'=>1],
            ],
            'country'=> [
                1 => ['name' => 'Latvia'],
                2 => ['name' => 'UK'],
            ],
        ];

        $p = new Persistence\Array_($a);

        $country = new Model($p, 'country');
        $country->addField('name');

        $user = new Model();
        $user->table = 'user';
        $user->addField('name');
        $user->addField('surname');

        $country->hasMany('Users', $user);
        $user->hasOne('country_id', $country);

        $country->load(1);
        $this->assertEquals(2, $country->ref('Users')->action('count')->getOne());

        $country->load(2);
        $this->assertEquals(1, $country->ref('Users')->action('count')->getOne());
    }
}
