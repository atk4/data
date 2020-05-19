<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\core\Translator\Translator;
use atk4\data\Exception;
use atk4\data\Locale;
use atk4\data\Model;
use atk4\data\Persistence;

class LocaleTest extends AtkPhpunit\TestCase
{
    public function testException()
    {
        $this->expectException(Exception::class);
        $exc = new Locale();
    }

    public function testGetPath()
    {
        $rootDir = realpath(dirname(__DIR__) . '/src/..');
        $this->assertEquals($rootDir . DIRECTORY_SEPARATOR . 'locale', realpath(Locale::getPath()));
    }

    public function testLocaleIntegration()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $trans = Translator::instance();
        $trans->setDefaultLocale('ru');

        try {
            $p = new Persistence\Array_($a);
            $m = new Model($p, 'user');
            $m->addField('name');
            $m->addField('surname');
            $m->load(4);
        } catch (Exception $e) {
            $this->assertStringContainsString('Запись', json_decode($e->getJSON(), true)['message']);

            return;
        }

        $this->fail('Expected exception');
    }
}
