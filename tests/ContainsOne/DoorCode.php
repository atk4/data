<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model;

/**
 * DoorCode model.
 *
 * @property string    $code       @Atk\Field()
 * @property \DateTime $valid_till @Atk\Field()
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
