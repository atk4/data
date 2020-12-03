<?php

declare(strict_types=1);

namespace atk4\data\Tests;

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
        $this->assertSame($rootDir . \DIRECTORY_SEPARATOR . 'locale', realpath(Locale::getPath()));
    }

    public function testLocaleIntegration()
    {
        $trans = Translator::instance();
        $trans->setDefaultLocale('ru');

        $p = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ]);

        try {
            $m = new Model($p, 'user');
            $m->addField('name');
            $m->addField('surname');
            $m->load(4);
        } catch (Exception $e) {
            $this->assertStringContainsString('Запись', json_decode($e->getJson(), true)['message']);

            return;
        }

        $this->fail('Expected exception');
    }
}
