<?php

namespace atk4\data;

class Field {
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    public function getDefault() 
    {
        return null;
    }
}

