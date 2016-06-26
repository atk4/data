<?php

namespace atk4\data;

class Persistence {
    use \atk4\core\ContainerTrait {
        add as _add;
    }
    use \atk4\core\HookTrait;


    public static function connect($dsn, $user = null, $password = null, $args = [])
    {
        if (strpos($dsn,':') === false) {
            throw new Exception(["Your DSN format is invalid. Must be in 'driver:host:options' format", 'dsn'=>$dsn]);
        }
        $driver = explode(':', $dsn, 2)[0];

        switch (strtolower(isset($args['driver']) ?: $driver)) {
            case 'mysql':
            case 'sqlite':
                return new Persistence_SQL($dsn, $user, $password, $args);
            default:
                throw new Exception([
                    'Unable to determine pesistence driver from DSN',
                    'dsn'=>$dsn
                ]);
        }
    }







    /**
     * Associate model with the data driver
     */
    public function add($m, $defaults = [])
    {
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }

        if ($m->persistence) {
            throw new Exception([
                'Model already has conditions or is related to persistance'
            ]);
        }

        if (is_object($m)) {
            $m->setDefaults($defaults);
        }

        $m = $this->_add($m, $defaults);
        $m->persistence = $this;
        $m->persistence_data = [];
        return $m;
    }

}
