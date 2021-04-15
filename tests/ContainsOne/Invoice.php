<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model;

/**
 * Invoice model.
 *
 * @property string  $ref_no @Atk4\Field()
 * @property Address $addr   @Atk4\RefOne()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent:: init();

        $this->title_field = $this->fieldName()->ref_no;

        $this->addField($this->fieldName()->ref_no, ['required' => true]);

        // will contain one Address
        $this->containsOne($this->fieldName()->addr, ['model' => [Address::class]]);
    }
}
