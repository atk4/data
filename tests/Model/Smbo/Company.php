<?php

declare(strict_types=1);

namespace atk4\data\tests\Model\Smbo;

use atk4\data\Model;

class Company extends Model
{
    public $table = 'system';

    protected function init(): void
    {
        parent::init();

        // Company data is stored in 3 tables actually.
        $j_contractor = $this->join('contractor');
        $j_company = $j_contractor->join('company.contractor_id', ['prefix' => 'company_']);

        $j_contractor->addFields([
            ['name', 'actual' => 'legal_name'],
        ]);

        $j_company->addFields([
            ['business_start', 'type' => 'date'],
            ['director_name'],
            ['vat_calculation_type', 'enum' => ['cash', 'invoice']],
        ]);

        $this->addFields([
            ['is_vat_registered', 'type' => 'boolean'],
        ]);
    }
}
