<?php

namespace atk4\data;

class Persistence {
    use \atk4\core\ContainerTrait {
        add as _add;
    }
    use \atk4\core\HookTrait;

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
