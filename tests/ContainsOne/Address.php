<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\ContainsOne;

use Atk4\Data\Model;

/**
 * @property Country            $country_id @Atk4\RefOne()
 * @property string             $address    @Atk4\Field()
 * @property \DateTime          $built_date @Atk4\Field()
 * @property array<int, string> $tags       @Atk4\Field()
 * @property DoorCode           $door_code  @Atk4\RefOne()
 */
class Address extends Model
{
    protected function init(): void
    {
        parent::init();

        $this->hasOne($this->fieldName()->country_id, ['model' => [Country::class], 'type' => 'integer']);

        $this->addField($this->fieldName()->address);
        $this->addField($this->fieldName()->built_date, ['type' => 'datetime']);
        $this->addField($this->fieldName()->tags, ['type' => 'json', 'default' => []]);

        $this->containsOne($this->fieldName()->door_code, ['model' => [DoorCode::class], 'caption' => 'Secret Code']);
    }
}
