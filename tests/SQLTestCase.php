<?php

namespace atk4\data\tests;

use atk4\data\Persistence_SQL;
use atk4\dsql\Query;

class SQLTestCase extends TestCase
{
    public $db;

    public $tables = null;

    public $debug = false;

    public function setUp()
    {
        parent::setUp();
        // establish connection
        $this->db = new Persistence_SQL(($this->debug ? ('dumper:') : '').'sqlite::memory:');
    }

    /**
     * Sets database into a specific test.
     */
    public function setDB($db_data)
    {
        $this->tables = array_keys($db_data);

        // create databases
        foreach ($db_data as $table => $data) {
            $s = new Structure(['connection' => $this->db->connection]);
            $s->table($table)->drop();

            $first_row = current($data);

            foreach ($first_row as $field => $row) {
                if ($field === 'id') {
                    $s->id('id');
                    continue;
                }

                if (is_int($row)) {
                    $s->field($field, ['type' => 'integer']);
                    continue;
                }

                $s->field($field);
            }

            if (!isset($first_row['id'])) {
                $s->id();
            }

            $s->create();

            $has_id = (bool) key($data);

            foreach ($data as $id => $row) {
                $s = new Query(['connection' => $this->db->connection]);
                if ($id === '_') {
                    continue;
                }

                $s->table($table);
                $s->set($row);

                if (!isset($row['id']) && $has_id) {
                    $s->set('id', $id);
                }

                $s->insert();
            }
        }
    }

    public function getDB($tables = null, $noid = false)
    {
        if (!$tables) {
            $tables = $this->tables;
        }

        if (is_string($tables)) {
            $tables = array_map('trim', explode(',', $tables));
        }

        $ret = [];

        foreach ($tables as $table) {
            $data2 = [];

            $s = new Query(['connection' => $this->db->connection]);
            $data = $s->table($table)->get();

            foreach ($data as &$row) {
                foreach ($row as &$val) {
                    if (is_int($val)) {
                        $val = (int) $val;
                    }
                }

                if ($noid) {
                    unset($row['id']);
                    $data2[] = $row;
                } else {
                    $data2[$row['id']] = $row;
                }
            }

            $ret[$table] = $data2;
        }

        return $ret;
    }

    public function runBare()
    {
        try {
            return parent::runBare();
        } catch (\atk4\core\Exception $e) {
            throw new \atk4\data\tests\AgileExceptionWrapper($e->getMessage(), 0, $e);
        }
    }

    public function callProtected($obj, $name, array $args = [])
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    public function getProtected($obj, $name)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getProperty($name);
        $method->setAccessible(true);

        return $method->getValue($obj);
    }
}
