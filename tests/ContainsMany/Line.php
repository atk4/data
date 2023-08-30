<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsMany;

use Atk4\Data\Model;

/**
 * @property VatRate   $vat_rate_id       @Atk4\RefOne()
 * @property float     $price             @Atk4\Field()
 * @property float     $qty               @Atk4\Field()
 * @property \DateTime $add_date          @Atk4\Field()
 * @property float     $total_gross       @Atk4\Field()
 * @property Discount  $discounts         @Atk4\RefMany()
 * @property float     $discounts_percent @Atk4\Field()
 */
class Line extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->hasOne($this->fieldName()->vat_rate_id, ['model' => [VatRate::class]]);

        $this->addField($this->fieldName()->price, ['type' => 'atk4_money', 'required' => true]);
        $this->addField($this->fieldName()->qty, ['type' => 'float', 'required' => true]);
        $this->addField($this->fieldName()->add_date, ['type' => 'datetime']);

        $this->addExpression($this->fieldName()->total_gross, ['expr' => static function (self $m) {
            return $m->price * $m->qty * (1 + $m->vat_rate_id->rate / 100);
        }, 'type' => 'float']);

        // each line can have multiple discounts and calculate total of these discounts
        $this->containsMany($this->fieldName()->discounts, ['model' => [Discount::class]]);

        $this->addCalculatedField($this->fieldName()->discounts_percent, ['expr' => static function (self $m) {
            $total = 0;
            foreach ($m->discounts as $d) {
                $total += $d->percent;
            }

            return $total;
        }, 'type' => 'float']);
    }
}
