<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Person;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class CsvTest extends AtkPhpunit\TestCase
{
    /** @var \SplFileObject */
    protected $file;

    /** @var \SplFileObject */
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = $this->makeCsvFileObject();
        $this->file2 = $this->makeCsvFileObject();
    }

    protected function makeCsvFileObject()
    {
        $fileObject = new \SplFileObject('php://memory', 'w+');

        $fileObject->setFlags(
            \SplFileObject::READ_CSV |
            \SplFileObject::SKIP_EMPTY |
            \SplFileObject::DROP_NEW_LINE |
            \SplFileObject::READ_AHEAD
        );

        return $fileObject;
    }

    protected function setDb($data): void
    {
        $this->file->ftruncate(0);
        $this->file->fputcsv(array_keys(reset($data)));
        foreach ($data as $row) {
            $this->file->fputcsv($row);
        }

        $this->file2->ftruncate(0);
    }

    protected function getDb(): array
    {
        $this->file->fseek(0);
        $keys = $this->file->fgetcsv();
        $data = [];
        while ($row = $this->file->fgetcsv()) {
            $data[] = array_combine($keys, $row);
        }

        return $data;
    }

    /**
     * Test constructor.
     */
    public function testTestcase()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);
        $data2 = $this->getDb();
        $this->assertSame($data, $data2);
    }

    public function testLoadAny()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();

        $this->assertSame('John', $m->get('name'));
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testLoadAnyException()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->load(2);

        $this->assertSame('Sarah', $m->get('name'));
        $this->assertSame('Jones', $m->get('surname'));

        $m->tryLoad(3);
        $this->assertFalse($m->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $p2 = new Persistence\Csv($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            (clone $m2)->save($row->get());
        }

        $this->file->fseek(0);
        $this->file2->fseek(0);
        $this->assertSame(
            $this->file->fread(5000),
            $this->file2->fread(5000)
        );
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
