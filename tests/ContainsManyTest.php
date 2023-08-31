<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Tests\ContainsMany\Invoice;
use Atk4\Data\Tests\ContainsMany\Line;
use Atk4\Data\Tests\ContainsMany\VatRate;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsMany(Line)
 *     - hasOne(VatRate, SQL)
 *     - containsMany(Discount)
 */
class ContainsManyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator(new VatRate($this->db))->create();
        $this->createMigrator(new Invoice($this->db))->create();

        $m = new VatRate($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->name => '21% rate',
                $m->fieldName()->rate => 21,
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->name => '15% rate',
                $m->fieldName()->rate => 15,
            ],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->ref_no => 'A1',
                $m->fieldName()->amount => 123,
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->ref_no => 'A2',
                $m->fieldName()->amount => 456,
            ],
        ]);
    }

    public function testModelCaption(): void
    {
        $i = new Invoice($this->db);

        // test caption of containsMany reference
        self::assertSame('My Invoice Lines', $i->getField($i->fieldName()->lines)->getCaption());
        self::assertSame('My Invoice Lines', $i->refModel($i->fieldName()->lines)->getModelCaption());
        self::assertSame('My Invoice Lines', $i->lines->getModelCaption());
    }

    public function testContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        self::assertSame(Line::class, get_class($i->getModel()->lines));

        // now let's add some lines
        $l = $i->lines;
        $rows = [
            1 => [
                $l->fieldName()->id => 1,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 10,
                $l->fieldName()->qty => 2,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 2,
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 15,
                $l->fieldName()->qty => 5,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 3,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 40,
                $l->fieldName()->qty => 1,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
        ];

        foreach ($rows as $row) {
            $l->insert($row);
        }

        // reload invoice just in case
        self::assertSameExportUnordered($rows, $i->lines->export());
        $i->reload();
        self::assertSameExportUnordered($rows, $i->lines->export());
        $i = $i->getModel()->load($i->getId());
        self::assertSameExportUnordered($rows, $i->lines->export());

        // now let's delete line with id=2 and add one more line
        $i->lines
            ->load(2)->delete()->getModel()
            ->insert([
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 50,
                $l->fieldName()->qty => 3,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ]);
        $rows = [
            1 => [
                $l->fieldName()->id => 1,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 10,
                $l->fieldName()->qty => 2,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            3 => [
                $l->fieldName()->id => 3,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 40,
                $l->fieldName()->qty => 1,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 4,
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 50,
                $l->fieldName()->qty => 3,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
        ];
        self::assertSameExportUnordered($rows, $i->lines->export());

        // try hasOne reference
        $v = $i->lines->load(4)->vat_rate_id;
        self::assertSame(15, $v->rate);

        // test expression fields
        $v = $i->lines->load(4);
        self::assertSame(50 * 3 * (1 + 15 / 100), $v->total_gross);

        // and what about calculated field?
        $i->reload(); // we need to reload invoice for changes in lines to be recalculated
        self::assertSame(10 * 2 * (1 + 21 / 100) + 40 * 1 * (1 + 21 / 100) + 50 * 3 * (1 + 15 / 100), $i->total_gross); // = 245.1
    }

    public function testNestedContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // now let's add some lines
        $l = $i->lines;

        $rows = [
            1 => [
                $l->fieldName()->id => 1,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 10,
                $l->fieldName()->qty => 2,
                $l->fieldName()->add_date => new \DateTime('2019-06-01'),
            ],
            [
                $l->fieldName()->id => 2,
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 15,
                $l->fieldName()->qty => 5,
                $l->fieldName()->add_date => new \DateTime('2019-07-01'),
            ],
        ];
        foreach ($rows as $row) {
            $l->insert($row);
        }

        // add some discounts
        $l->load(1)->discounts->insert([
            $l->discounts->fieldName()->id => 1,
            $l->discounts->fieldName()->percent => 5,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-15'),
        ]);
        $l->load(1)->discounts->insert([
            $l->discounts->fieldName()->id => 2,
            $l->discounts->fieldName()->percent => 10,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-30'),
        ]);
        $l->load(2)->discounts->insert([
            $l->discounts->fieldName()->id => 1,
            $l->discounts->fieldName()->percent => 20,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-12-31'),
        ]);

        // reload invoice to be sure all is saved and to recalculate all fields
        $i->reload();

        // ok, so now let's test
        self::assertSameExportUnordered([
            1 => [
                $l->discounts->fieldName()->id => 1,
                $l->discounts->fieldName()->percent => 5,
                $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-15'),
            ],
            [
                $l->discounts->fieldName()->id => 2,
                $l->discounts->fieldName()->percent => 10,
                $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-30'),
            ],
        ], $i->lines->load(1)->discounts->export());

        // is total_gross correctly calculated?
        self::assertSame(10 * 2 * (1 + 21 / 100) + 15 * 5 * (1 + 15 / 100), $i->total_gross); // =110.45

        // do we also correctly calculate discounts from nested containsMany?
        self::assertSame(24.2 * 15 / 100 + 86.25 * 20 / 100, $i->discounts_total_sum); // =20.88

        // let's test how it all looks in persistence without typecasting
        $exportLines = $i->getModel()->setOrder($i->fieldName()->id)
            ->export(null, null, false)[0][$i->fieldName()->lines];
        $formatDtForCompareFx = static function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore-line

            return $dt->format('Y-m-d H:i:s.u');
        };
        self::assertJsonStringEqualsJsonString(
            json_encode([
                1 => [
                    $i->lines->fieldName()->id => 1,
                    $i->lines->fieldName()->vat_rate_id => 1,
                    $i->lines->fieldName()->price => '10',
                    $i->lines->fieldName()->qty => 2,
                    $i->lines->fieldName()->add_date => $formatDtForCompareFx(new \DateTime('2019-06-01')),
                    $i->lines->fieldName()->discounts => json_encode([
                        1 => [
                            $i->lines->discounts->fieldName()->id => 1,
                            $i->lines->discounts->fieldName()->percent => 5,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-07-15')),
                        ],
                        [
                            $i->lines->discounts->fieldName()->id => 2,
                            $i->lines->discounts->fieldName()->percent => 10,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-07-30')),
                        ],
                    ]),
                ],
                [
                    $i->lines->fieldName()->id => 2,
                    $i->lines->fieldName()->vat_rate_id => 2,
                    $i->lines->fieldName()->price => '15',
                    $i->lines->fieldName()->qty => 5,
                    $i->lines->fieldName()->add_date => $formatDtForCompareFx(new \DateTime('2019-07-01')),
                    $i->lines->fieldName()->discounts => json_encode([
                        1 => [
                            $i->lines->discounts->fieldName()->id => 1,
                            $i->lines->discounts->fieldName()->percent => 20,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-12-31')),
                        ],
                    ]),
                ],
            ]),
            $exportLines
        );
    }

    public function testUnmanagedDataModificationException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ContainsXxx does not support unmanaged data modification');
        $i->set($i->fieldName()->lines, [0]);
    }
}
