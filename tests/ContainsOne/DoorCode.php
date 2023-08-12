<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model;

/**
 * @property string    $code       @Atk4\Field()
 * @property \DateTime $valid_till @Atk4\Field()
 */
class DoorCode extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->code);
        $this->addField($this->fieldName()->valid_till, ['type' => 'datetime']);
    }
}
