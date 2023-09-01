<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model;

/**
 * @property string  $ref_no @Atk4\Field()
 * @property Address $addr   @Atk4\RefOne()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->titleField = $this->fieldName()->ref_no;

        $this->addField($this->fieldName()->ref_no, ['required' => true]);

        $this->containsOne($this->fieldName()->addr, ['model' => [Address::class]]);
    }
}
