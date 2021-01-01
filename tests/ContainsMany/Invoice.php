<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsMany;

use Atk4\Data\Model;

/**
 * Invoice model.
 *
 * @property string $ref_no              @Atk\Field()
 * @property float  $amount              @Atk\Field()
 * @property Line   $lines               @Atk\RefOne()
 * @property string $total_gross         @Atk\Field()
 * @property float  $discounts_total_sum @Atk\Field()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent:: init();

        $this->title_field = $this->fieldName()->ref_no;

        $this->addField($this->fieldName()->ref_no, ['required' => true]);
        $this->addField($this->fieldName()->amount, ['type' => 'money']);

        // will contain many Lines
        $this->containsMany($this->fieldName()->lines, ['model' => [Line::class], 'caption' => 'My Invoice Lines']);

        // total_gross - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->fieldName()->total_gross, function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += $line->total_gross;
            }

            return $total;
        });

        // discounts_total_sum - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->fieldName()->discounts_total_sum, function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += $line->total_gross * $line->discounts_percent / 100;
            }

            return $total;
        });
    }
}
