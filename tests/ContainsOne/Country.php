<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model2;

/**
 * @property string $name @Atk4\Field()
 */
class Country extends Model2
{
    public $table = 'country';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->name);
    }
}
