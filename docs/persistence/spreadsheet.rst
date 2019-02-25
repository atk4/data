
.. _Persistence_PhpSpreadsheet:

============================
Loading and Saving using PhpSpreadsheet
============================

.. php:class:: Persistence_PhpSpreadsheet

Agile Data can operate using PhpSpreadsheet library as a data storage.

The capabilities of Persistence_PhpSpreadsheet are limited to the following actions

- reading extension :
    - xlsx - Excel (OfficeOpenXML) Spreadsheet
    - xlsm - Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
    - xltx - Excel (OfficeOpenXML) Template
    - xltm - Excel (OfficeOpenXML) Macro Template (macros will be discarded)
    - xls - Excel (BIFF) Spreadsheet
    - xlt - Excel (BIFF) Template
    - ods - Open/Libre Offic Calc
    - ots - Open/Libre Offic Calc Template
    - slk - Symbolic Link (SYLK) Microsoft
    - xml - Excel 2003 SpreadSheetML
    - gnumeric - Gnome Gnumeric spreadsheet
    - htm & html - HyperText Markup language
    - csv - Comma Separated Values

- writing extension :
    - xlsx - Excel (OfficeOpenXML) Spreadsheet
    - xlsm - Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
    - xltx - Excel (OfficeOpenXML) Template
    - xltm - Excel (OfficeOpenXML) Macro Template (macros will be discarded)
    - xls - Excel (BIFF) Spreadsheet
    - xlt - Excel (BIFF) Template
    - ods - Open/Libre Offic Calc
    - ots - Open/Libre Offic Calc Template
    - htm & html - HyperText Markup language
    - pdf - Portable Document Format (Adobe Acrobat) (needs additional @see Writing PDF)

- identify which column is corresponding for respective field
- support custom mapper, e.g. if date is stored in a weird way
- listing/iterating
- adding a new record
- reading and writing on the same file, different extension
- save persistence using another writing extension with method saveSheetAs
- select the index of worksheet to read and write ( only xlsx,xls,ods)
- change the title of worksheet to read and write ( only xlsx,xls,ods)
- get the spreadsheet and use it with functions of PhpSpreadsheet ( it will be a clone of the inner spreadsheet to preserve stability)

Setting Up
==========

When creating new persistence, you must provide at least a filename.
If filename not exist, it will open an empty spreadsheet.

The file will not be actually opened unless you perform load/save operation::

    $p = new Persistence_PhpSpreadsheet('myfile.csv');

    $u = new Model_User($p);
    $u->tryLoadAny();   // actually opens file and finds first record

Different way of setting up
===========================

read and write on file.xls :

    $p = new Persistence_PhpSpreadsheet('file.xls');

same as
	$p = new Persistence_PhpSpreadsheet('file.xls','file.xls');

read file.csv and write on other.xls

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls');

read file.csv and write on other.xls on sheet index 1

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', 1);

read file.csv and write on other.xls on sheet index 2

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', 2);

*ATTENTION* Our worksheet index are not ZeroBased

read file.csv and write on other.xls add sheet before first sheet

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', -1);

read file.csv and write on other.xls add sheet after last sheet

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', null);
read and write on file.xls :

	$p = new Persistence_PhpSpreadsheet('file.xls');

same as
	$p = new Persistence_PhpSpreadsheet('file.xls','file.xls');

read file.csv and write on other.xls

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls');

read file.csv and write on other.xls on sheet index 1

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', 1);

read file.csv and write on other.xls on sheet index 2

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', 2);

*ATTENTION* Our worksheet index are not ZeroBased

read file.csv and write on other.xls add sheet before first sheet

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', -1);

read file.csv and write on other.xls add sheet after last sheet

	$p = new Persistence_PhpSpreadsheet('file.csv','other.xls', null);

Exporting and Importing data
=====================================

You can take a model that is loaded from other persistence and save
it into CSV like this. The next example demonstrates a basic functionality
of SQL database export to CSV file::

    $db = new Persistence_SQL($pdo);
    $csv = new Persistence_PhpSpreadsheet('dump.csv');

    $m = new Model_User($db);

    foreach (new Model_User($db) as $m) {
        $m->withPersistence($csv)->save();
    }

Theoretically you can do few things to tweak this process. You can specify
which fields you would like to see in the CSV::

    foreach (new Model_User($db) as $m) {
        $m->withPersistence($csv)
            ->onlyFields(['id','name','password'])
            ->save();
    }

Additionally if you want to use a different column titles, you can::

    foreach (new Model_User($db) as $m) {
        $m_csv = $m->withPersistence($csv);
        $m_csv->onlyFields(['id', 'name', 'password'])
        $m_csv->getElement('name')->actual = 'First Name';
        $m_csv->save();
    }

Like with any other persistence you can use typecasting if you want data to be
stored in any particular format.

The examples above also create object on each iteration, that may appear as
a performance inefficiency. This can be solved by re-using CSV model through
iterations::

    $m = new Model_User($db);
    $m_csv = $m->withPersistence($csv);
    $m_csv->onlyFields(['id', 'name', 'password'])
    $m_csv->getElement('name')->actual = 'First Name';

    foreach ($m as $m_csv) {
        $m_csv->save();
    }

This code can be further simplified if you use import() method::

    $m = new Model_User($db);
    $m_csv = $m->withPersistence($csv);
    $m_csv->onlyFields(['id', 'name', 'password'])
    $m_csv->getElement('name')->actual = 'First Name';
    $m_csv->import($m);

Naturally you can also move data in the other direction::

    $m = new Model_User($db);
    $m_csv = $m->withPersistence($csv);
    $m_csv->onlyFields(['id', 'name', 'password'])
    $m_csv->getElement('name')->actual = 'First Name';

    $m->import($m_csv);

Only the last line changes and the data will now flow in the other direction.

============
Writing PDF
============

read file.csv and write on other.pdf writer needs to be specified

$p = new Persistence_PhpSpreadsheet('file.csv','other.pdf', 1, 'Dompdf');

.. list-table:: Additional library needed by pdf
   :widths: 10 10 30
   :header-rows: 1

   * - Library
     - Writer
     - GitHub url
   * - TCPDF
     - Tcpdf
     - https://github.com/tecnickcom/tcpdf
   * - mPDF
     - Mpdf
     - https://github.com/mpdf/mpdf
   * - Dompdf
     - Dompdf
     - https://github.com/dompdf/dompdf
