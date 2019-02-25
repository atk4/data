<?php
/**
 * Copyright (c) 2019.
 *
 * Francesco "Abbadon1334" Danti <fdanti@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

namespace atk4\data\tests;

use \atk4\data\Model;
use \atk4\data\Persistence_PhpSpreadsheet;
use \atk4\data\tests\Model\Person as Person;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class SpreadsheetTest extends \atk4\core\PHPUnit_AgileTestCase
{
    public $file = 'atk-test';
    public $file_alt = 'atk-test-alt';

    public function setDB($filename,$data)
    {
        $f = fopen($filename, 'w');
        fputcsv($f, array_keys(current($data)));
        foreach ($data as $row) {
            fputcsv($f, $row);
        }
        fclose($f);
    }

    public function tearDown()
    {
        // remove all file types
        $types = [
            Persistence_PhpSpreadsheet::READ_TYPE_XLS,
            Persistence_PhpSpreadsheet::READ_TYPE_XLSX,
            Persistence_PhpSpreadsheet::READ_TYPE_XML,
            Persistence_PhpSpreadsheet::READ_TYPE_ODS,
            Persistence_PhpSpreadsheet::READ_TYPE_SLK,
            Persistence_PhpSpreadsheet::READ_TYPE_GNU,
            Persistence_PhpSpreadsheet::READ_TYPE_CSV,
            Persistence_PhpSpreadsheet::READ_TYPE_HTML,
            
            Persistence_PhpSpreadsheet::WRITE_TYPE_XLS,
            Persistence_PhpSpreadsheet::WRITE_TYPE_XLSX,
            Persistence_PhpSpreadsheet::WRITE_TYPE_ODS,
            Persistence_PhpSpreadsheet::WRITE_TYPE_CSV,
            Persistence_PhpSpreadsheet::WRITE_TYPE_HTML,
            Persistence_PhpSpreadsheet::WRITE_TYPE_PDF
        ];
        
        // remove duplicated types
        $types = array_unique($types);
        
        foreach($types as $type)
        {
            // add is_file to be sure
            try {
                
                $file = $this->file . '.' . strtolower($type);
                if(is_file($file))
                {
                    unlink($file);
                }
            } catch (\Exception $e) {
            }
    
            try {
                $file_alt = $this->file_alt . '.' . strtolower($type);
                if(is_file($file_alt))
                {
                    unlink($file_alt);
                }
            } catch (\Exception $e) {
            }
        }
    }

    public function getDB()
    {
        $f = fopen($this->file . '.csv', 'r');
        $keys = fgetcsv($f);
        $data = [];
        while ($row = fgetcsv($f)) {
            $data[] = array_combine($keys, $row);
        }
        fclose($f);

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

        $this->setDB($this->file . '.csv',$data);
        $data2 = $this->getDB();
        $this->assertEquals($data, $data2);
    }

    public function testLoadAny()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ];

        $this->setDB($this->file . '.csv',$data);

        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();

        $this->assertEquals('John', $m['name']);
        $this->assertEquals('Smith', $m['surname']);
    }

    public function testLoadAnyException()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ];

        $this->setDB($this->file . '.csv',$data);

        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();
        $m->loadAny();

        $this->assertEquals('Sarah', $m['name']);
        $this->assertEquals('Jones', $m['surname']);

        $m->tryLoadAny();
        $this->assertFalse($m->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith', 'gender'=>'M'],
                ['name' => 'Sarah', 'surname' => 'Jones', 'gender'=>'F'],
            ];

        $this->setDB($this->file . '.csv', $data);

        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $p2 = new Persistence_PhpSpreadsheet($this->file_alt . '.csv');

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            $m2->save($m);
        }

        $this->assertEquals(
            file_get_contents($this->file_alt . '.csv'),
            file_get_contents($this->file . '.csv')
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
        
        $this->setDB($this->file . '.csv', $data);

        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertEquals([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
    
    /**
     * Test IReader if file not exists check allowed extensions
     */
    public function testPrepareReaderExtensionsNoFile()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        
        $this->setDB($this->file . '.csv', $data);
    
        $types = [
            Persistence_PhpSpreadsheet::READ_TYPE_XLS,
            Persistence_PhpSpreadsheet::READ_TYPE_XLSX,
            Persistence_PhpSpreadsheet::READ_TYPE_XML,
            Persistence_PhpSpreadsheet::READ_TYPE_ODS,
            Persistence_PhpSpreadsheet::READ_TYPE_SLK,
            Persistence_PhpSpreadsheet::READ_TYPE_GNU,
            Persistence_PhpSpreadsheet::READ_TYPE_CSV,
            Persistence_PhpSpreadsheet::READ_TYPE_HTML
        ];
        
        foreach($types as $type)
        {
            $type = strtolower($type);
            $p = new Persistence_PhpSpreadsheet($this->file . '.' . $type, $this->file . '.csv');
        
            $this->assertNotNull($p->getSpreadsheet(),'error on Type ' . $type);
        }
    
        // test file not allowed
        try {
            $p = new Persistence_PhpSpreadsheet($this->file . '.php');
            $this->fail("Expected for file type not allowed for IReader has not been raised.");
        } catch(\Throwable $t)
        {
            $this->assertEquals($t->getMessage(),'File type not allowed for IReader');
        }
    }
    
    /**
     * Test IWriter if file not exists check allowed extensions.
     */
    public function testPrepareWriterExtensionsNoFile()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
    
        $this->setDB($this->file . '.csv', $data);
    
        $types = [
            Persistence_PhpSpreadsheet::WRITE_TYPE_XLS,
            Persistence_PhpSpreadsheet::WRITE_TYPE_XLSX,
            Persistence_PhpSpreadsheet::WRITE_TYPE_ODS,
            Persistence_PhpSpreadsheet::WRITE_TYPE_CSV,
            Persistence_PhpSpreadsheet::WRITE_TYPE_HTML,
            Persistence_PhpSpreadsheet::WRITE_TYPE_PDF
        ];
        
        foreach($types as $type)
        {
            $type = strtolower($type);
            
            if($type=== 'pdf')
            {
                $p = new Persistence_PhpSpreadsheet($this->file . '.csv', $this->file . '.' . $type, 1, 'Dompdf');
            } else {
                $p = new Persistence_PhpSpreadsheet($this->file . '.csv',$this->file . '.' . $type);
            }
            
            $this->assertNotNull($p->getSpreadsheet(),'error on Type ' . $type);
        }
        
        // test file not allowed
        try {
            $p = new Persistence_PhpSpreadsheet($this->file . '.csv','file.php');
            $this->fail("Expected for file type not allowed for IWriter has not been raised.");
        } catch(\Throwable $t)
        {
            $this->assertEquals($t->getMessage(),'File type not allowed for IWriter');
        }
    }
    
    /**
     * Test if spreadsheet exists after call getSpreadsheet() from outside
     */
    public function testGetSpreadsheet()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
    
        $this->setDB($this->file . '.csv', $data);
        
        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $this->assertNotNull($p->getSpreadsheet(),'Spreadsheet not created after reading file');
    }
    
    /**
     * Test method SaveAs xls
     */
    public function testMethodSaveSheetAs()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        
        $this->setDB($this->file . '.csv',$data);
        
        $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
        $p->saveSheetAs($this->file . '.xls');
    }
    
    /**
     * Test method SaveAs pdf
     */
    public function testMethodSaveSheetAsPDF()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        
        $this->setDB($this->file . '.csv',$data);
        
        try {
            $p = new Persistence_PhpSpreadsheet($this->file . '.csv');
            $p->saveSheetAs($this->file . '.pdf');
            
            $this->fails('this test must throw an exception for not specifing PDFClass');
        } catch(\Throwable $t)
        {
            $this->assertEquals($t->getMessage(),'PDF writing needs to set PDFClass name in __construct or in saveSheetAs methods, value must be set to one this : Tcpdf | Mpdf | Dompdf');
        }
    
        try {
            $p->saveSheetAs($this->file . '.pdf','Dompdf');
        } catch(\Throwable $t)
        {
            $this->fail('this test must not throw an exception SaveAs PDF + PDFClass');
        }
    }
    
    /**
     * Test method check IO_direction
     */
    public function testRespectIODirection()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        
        $this->setDB($this->file . '.csv',$data);
        
        $p = new Persistence_PhpSpreadsheet($this->file . '.csv',$this->file_alt . '.csv');
    
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        
        $m->tryLoadAny();
        
        try {
            
            $insertData = [
                'name'    => 'new',
                'surname' => 'record'
            ];
            
            $m->insert($insertData);
            
            $this->fail('IO_Direction not respected');
        } catch(\Throwable $t)
        {
            $this->assertEquals($t->getMessage(),'IO Direction already set and cannot be changed');
        }
    }
    
    /**
     * Test method Change title on xls
     */
    public function testChangeTitleXls()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        
        $this->setDB($this->file . '.csv',$data);
        
        $title2set = 'new title';
        
        $p = new Persistence_PhpSpreadsheet($this->file . '.csv',$this->file . '.xls');
        $m = new Person($p);
        
        $p->setActiveSheetTitle($title2set);
        
        $p2 = new Persistence_PhpSpreadsheet($this->file . '.xls');
        
        $this->assertEquals(
            $title2set,
            $p2->getSpreadsheet()->getActiveSheet()->getTitle()
        );
    }
    
    /**
     * Test add new sheet before first sheet
     */
    public function testAddNewSheetBeforeFirstSheet()
    {
        $data = [
            ['name'    => 'John',
             'surname' => 'Smith'
            ],
            [
                'name'    => 'Sarah',
                'surname' => 'Jones'
            ],
        ];
    
        $this->setDB($this->file . '.csv', $data);
    
        $title2set = 'new title';
    
        $p = new Persistence_PhpSpreadsheet($this->file . '.csv', $this->file . '.xls', -1);
        $m = new Model($p);
    
        $p->setActiveSheetTitle($title2set);
    
        $p = new Persistence_PhpSpreadsheet($this->file . '.xls', $this->file . '.xls',1);
    
        $this->assertEquals($title2set, $p->getSpreadsheet()
                                          ->getActiveSheet()
                                          ->getTitle());
    }
    
    /**
     * Test add new sheet after last sheet
     */
    public function testAddNewSheetAfterLastSheet()
    {
        $data = [
            ['name'    => 'John',
             'surname' => 'Smith'
            ],
            [
                'name'    => 'Sarah',
                'surname' => 'Jones'
            ],
        ];
    
        $this->setDB($this->file . '.csv', $data);
    
        $title2set = 'new title';
    
        $p = new Persistence_PhpSpreadsheet($this->file_alt . '.csv', $this->file_alt . '.xls', null);
        $m = new Model($p);
    
        $p->setActiveSheetTitle($title2set);
    
        $p = new Persistence_PhpSpreadsheet($this->file_alt . '.xls', $this->file . '.xls',2);
    
        $this->assertEquals($title2set, $p->getSpreadsheet()
                                          ->getActiveSheet()
                                          ->getTitle());
    }
}
