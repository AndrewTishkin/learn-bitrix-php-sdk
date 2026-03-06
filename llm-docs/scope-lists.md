# Lists scope (lists)

## Оглавление

1. [Обзор](#1-обзор)
2. [Доступ к сервисам](#2-доступ-к-сервисам)
3. [Типы списков (IBLOCK_TYPE_ID)](#3-типы-списков-iblock_type_id)
4. [Lists — управление списком](#4-lists--управление-списком)
5. [Field — поля списка](#5-field--поля-списка)
6. [Section — разделы](#6-section--разделы)
7. [Element — элементы](#7-element--элементы)

---

## 1. Обзор

Lists scope позволяет работать с **универсальными списками** Битрикс24 — настраиваемыми справочниками и базами данных, построенными на инфоблоках.

Scope для прав: `lists`

---

## 2. Доступ к сервисам

```php
$listsScope = $b24->getListsScope();

$listsService   = $listsScope->lists();    // Управление списком (CRUD)
$fieldService   = $listsScope->field();    // Поля списка
$sectionService = $listsScope->section();  // Разделы (папки)
$elementService = $listsScope->element();  // Элементы списка
```

---

## 3. Типы списков (IBLOCK_TYPE_ID)

Каждый список привязан к типу инфоблока:

| IBLOCK_TYPE_ID | Описание |
|---|---|
| `'lists'` | Обычные списки (в разделе «Списки») |
| `'lists_socnet'` | Списки в группе/проекте социальной сети |
| `'bitrix_processes'` | Бизнес-процессы |

```php
// Получить возможные типы
$types = $b24->getListsScope()->lists()->getIBlockTypeId()->types();
// ['lists', 'lists_socnet', 'bitrix_processes']
```

---

## 4. Lists — управление списком

### Создать список

```php
$id = $listsScope->lists()->add(
    iblockTypeId: 'lists',
    iblockCode:   'my_catalog',
    fields: [
        'NAME'             => 'Мой справочник',
        'DESCRIPTION'      => 'Описание',
        'SORT'             => 100,
        'IS_ENABLED'       => 'Y',
        'LIST_PAGE_URL'    => '',
        'DETAIL_PAGE_URL'  => '',
    ],
    messages: [],     // пользовательские надписи
    rights:   [],     // права
    socnetGroupId: 0  // ID группы (для lists_socnet)
)->getId();
```

### Получить список

```php
$list = $listsScope->lists()->get(
    iblockTypeId: 'lists',
    filter: ['ID' => $listId]
)->list();
```

### Обновить список

```php
$listsScope->lists()->update(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fields: ['NAME' => 'Новое название'],
    messages: [],
    rights:   []
);
```

### Удалить список

```php
$listsScope->lists()->delete(
    iblockTypeId: 'lists',
    iblockId:     $listId
);
```

---

## 5. Field — поля списка

### Добавить поле

```php
$fieldId = $listsScope->field()->add(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fields: [
        'FIELD_TYPE'          => 'text',      // text, number, date, file, ...
        'FIELD_ID'            => 'MY_FIELD',  // код поля
        'NAME'                => ['ru' => 'Моё поле'],
        'IS_REQUIRED'         => 'N',
        'MULTIPLE'            => 'N',
        'SORT'                => 100,
    ]
)->getId();
```

### Получить поля списка

```php
$fields = $listsScope->field()->get(
    iblockTypeId: 'lists',
    iblockId:     $listId
)->fields();

foreach ($fields as $field) {
    echo $field['FIELD_ID'] . ': ' . $field['FIELD_TYPE'] . PHP_EOL;
}
```

### Обновить поле

```php
$listsScope->field()->update(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fieldId:      'MY_FIELD',
    fields: ['NAME' => ['ru' => 'Переименованное поле']]
);
```

### Удалить поле

```php
$listsScope->field()->delete(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fieldId:      'MY_FIELD'
);
```

---

## 6. Section — разделы

Разделы — папки для организации элементов внутри списка.

### Добавить раздел

```php
$sectionId = $listsScope->section()->add(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fields: [
        'NAME'      => 'Мой раздел',
        'SORT'      => 100,
        'IBLOCK_SECTION_ID' => 0,  // 0 = корневой раздел
    ]
)->getId();
```

### Получить разделы

```php
$sections = $listsScope->section()->get(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    filter: []
)->sections();
```

### Обновить раздел

```php
$listsScope->section()->update(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    sectionId:    $sectionId,
    fields: ['NAME' => 'Новое название']
);
```

### Удалить раздел

```php
$listsScope->section()->delete(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    sectionId:    $sectionId
);
```

---

## 7. Element — элементы

### Добавить элемент

```php
// iblockId может быть int или string (например, числовой код)
$elementId = $listsScope->element()->add(
    iblockTypeId:   'lists',
    iblockId:       $listId,   // int|string
    elementCode:    '',        // произвольный код элемента
    fields: [
        'NAME'        => 'Новый элемент',
        'PROPERTY_MY_FIELD' => 'значение',
    ],
    sectionId:      0,         // 0 = корень
    listElementUrl: ''
)->getId();
```

### Получить элементы (с фильтром, сортировкой, пагинацией)

```php
$result = $listsScope->element()->get(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    select: ['ID', 'NAME', 'PROPERTY_MY_FIELD'],
    filter: ['PROPERTY_STATUS' => 'active'],
    order:  ['ID' => 'ASC'],
    pagination: ['start' => 0]
);

foreach ($result->elements() as $element) {
    echo $element['ID'] . ': ' . $element['NAME'] . PHP_EOL;
}
```

### Обновить элемент

```php
$listsScope->element()->update(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    elementId:    $elementId,  // int|string
    fields: ['PROPERTY_MY_FIELD' => 'новое значение']
);
```

### Удалить элемент

```php
$listsScope->element()->delete(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    elementId:    $elementId   // int|string
);
```

### Получить URL файла

Для полей типа `file` — получить публичный URL загруженного файла:

```php
$url = $listsScope->element()->getFileUrl(
    iblockTypeId: 'lists',
    iblockId:     $listId,
    fieldId:      'MY_FILE_FIELD',
    elementId:    $elementId,
    fileId:       $fileId
)->url();
```
