<?php

return [
  'Field value can not be base64 encoded because it is not of string type' => 'Значение поля не может быть закодировано в base64, поскольку оно не имеет строкового типа ({{field}})',
  'Mandatory field value cannot be null'                                   => 'Обязательное значение поля не может быть пустым ({{field}})',
  'Model is already related to another persistence'                        => 'Модель уже связана с другим постоянством',
  'Test with plural'                                                       => [
    'zero'  => 'Тест ноль',
    'one'   => 'Тест один',
    'other' => 'Тест {{count}}',
  ],
  'There was error while decoding JSON'             => 'Произошла ошибка при декодировании JSON',
  'Unable to determine persistence driver from DSN' => 'Невозможно определить постоянство драйвера из DSN',
  'Unable to serialize field value on load'         => 'Невозможно сериализовать значение поля при загрузке ({{field}})',
  'Unable to serialize field value on save'         => 'Невозможно сериализовать значение поля при сохранении ({{field}})',
  'Unable to typecast field value on load'          => 'Невозможно установить тип поля при загрузке ({{field}})',
  'Unable to typecast field value on save'          => 'Невозможно сериализовать значение поля при сохранении ({{field}})',
];
