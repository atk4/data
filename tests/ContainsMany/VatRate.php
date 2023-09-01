<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsMany;

use Atk4\Data\Model;

/**
 * @property string $name @Atk4\Field()
 * @property int    $rate @Atk4\Field()
 */
class VatRate extends Model
{
    public $table = 'vat_rate';

    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->name);
        $this->addField($this->fieldName()->rate, ['type' => 'integer']);
    }
}
