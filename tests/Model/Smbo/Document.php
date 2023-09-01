<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model\Smbo;

use Atk4\Data\Model;

class Document extends Model
{
    public $table = 'document';

    protected function init(): void
    {
        parent::init();

        $this->addField('doc_type', ['enum' => ['invoice', 'payment']]);
        $this->addField('amount', ['type' => 'atk4_money']);
    }
}
