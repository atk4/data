<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * Various tests to make sure transactions work OK.
 */
class TransactionTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testAtomicOperations()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'item');
        $m->addField('name');
        $m->load(2);

        $m->addHook('afterSave', function ($m) {
            throw new \Exception('Awful thing happened');
        });
        $m['name'] = 'XXX';

        try {
            $m->save();
        } catch (\Exception $e) {
        }

        $this->assertEquals('Sue', $this->getDB()['item'][2]['name']);

        $m->addHook('afterDelete', function ($m) {
            throw new \Exception('Awful thing happened');
        });

        try {
            $m->delete();
        } catch (\Exception $e) {
        }

        $this->assertEquals('Sue', $this->getDB()['item'][2]['name']);
    }
}
