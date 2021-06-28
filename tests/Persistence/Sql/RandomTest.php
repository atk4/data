<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Mysql;
use Atk4\Data\Persistence\Sql\Oracle;
use Atk4\Data\Persistence\Sql\Postgresql;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Persistence\Sql\Sqlite;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Query
 */
class RandomTest extends TestCase
{
    /**
     * @param string|array ...$args
     */
    public function q(...$args): Query
    {
        return new Query(...$args);
    }

    public function testMiscInsert(): void
    {
        $data = [
            'id' => null,
            'system_id' => '3576',
            'system' => null,
            'created_dts' => 123,
            'contractor_from' => null,
            'contractor_to' => null,
            'vat_rate_id' => null,
            'currency_id' => null,
            'vat_period_id' => null,
            'journal_spec_id' => '147735',
            'job_id' => '9341',
            'nominal_id' => null,
            'root_nominal_code' => null,
            'doc_type' => null,
            'is_cn' => 'N',
            'doc_date' => null,
            'ref_no' => '940 testingqq11111',
            'po_ref' => null,
            'total_gross' => '100.00',
            'total_net' => null,
            'total_vat' => null,
            'exchange_rate' => null,
            'note' => null,
            'archive' => 'N',
            'fx_document_id' => null,
            'exchanged_total_net' => null,
            'exchanged_total_gross' => null,
            'exchanged_total_vat' => null,
            'exchanged_total_a' => null,
            'exchanged_total_b' => null,
        ];
        $q = $this->q();
        $q->mode('insert');
        foreach ($data as $key => $val) {
            $q->set($key, $val);
        }
        $this->assertSame(
            'insert into  ("' . implode('", "', array_keys($data)) . '") values (:a, :b, :c, :d, :e, :f, :g, :h, :i, :j, :k, :l, :m, :n, :o, :p, :q, :r, :s, :t, :u, :v, :w, :x, :y, :z, :aa, :ab, :ac, :ad)',
            $q->render()
        );
    }

    /**
     * Confirms that group concat works for all the SQL vendors we support.
     */
    public function _groupConcatTest(string $expected, Query $q): void
    {
        $q->table('people');
        $q->group('age');

        $q->field('age');
        $q->field($q->groupConcat('name', ','));

        $q->groupConcat('name', ',');

        $this->assertSame($expected, $q->render());
    }

    public function testGroupConcat(): void
    {
        $this->_groupConcatTest(
            'select `age`, group_concat(`name` separator :a) from `people` group by `age`',
            new Mysql\Query()
        );

        $this->_groupConcatTest(
            'select "age", group_concat("name", :a) from "people" group by "age"',
            new Sqlite\Query()
        );

        $this->_groupConcatTest(
            'select "age", string_agg("name", :a) from "people" group by "age"',
            new Postgresql\Query()
        );

        $this->_groupConcatTest(
            'select "age", listagg("name", :a) within group (order by "name") from "people" group by "age"',
            new Oracle\Query()
        );
    }
}
