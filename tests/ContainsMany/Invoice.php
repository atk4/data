<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsMany;

use Atk4\Data\Model;

/**
 * @property string $ref_no              @Atk4\Field()
 * @property float  $amount              @Atk4\Field()
 * @property Line   $lines               @Atk4\RefMany()
 * @property float  $total_gross         @Atk4\Field()
 * @property float  $discounts_total_sum @Atk4\Field()
 */
class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->titleField = $this->fieldName()->ref_no;

        $this->addField($this->fieldName()->ref_no, ['required' => true]);
        $this->addField($this->fieldName()->amount, ['type' => 'atk4_money']);

        // will contain many Lines
        $this->containsMany($this->fieldName()->lines, ['model' => [Line::class], 'caption' => 'My Invoice Lines']);

        // total_gross - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->fieldName()->total_gross, ['expr' => static function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += $line->total_gross;
            }

            return $total;
        }, 'type' => 'float']);

        // discounts_total_sum - calculated by php callback not by SQL expression
        $this->addCalculatedField($this->fieldName()->discounts_total_sum, ['expr' => static function (self $m) {
            $total = 0;
            foreach ($m->lines as $line) {
                $total += $line->total_gross * $line->discounts_percent / 100;
            }

            return $total;
        }, 'type' => 'float']);
    }
}
