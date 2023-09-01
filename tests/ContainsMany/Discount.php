<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsMany;

use Atk4\Data\Model;

/**
 * @property int       $percent    @Atk4\Field()
 * @property \DateTime $valid_till @Atk4\Field()
 */
class Discount extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->percent, ['type' => 'integer', 'required' => true]);
        $this->addField($this->fieldName()->valid_till, ['type' => 'datetime']);
    }
}
