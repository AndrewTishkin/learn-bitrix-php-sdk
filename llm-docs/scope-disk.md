# Disk scope (disk)

## Оглавление

1. [Обзор](#1-обзор)
2. [Доступ к сервисам](#2-доступ-к-сервисам)
3. [Storage — хранилища](#3-storage--хранилища)
4. [Folder — папки](#4-folder--папки)
5. [File — файлы](#5-file--файлы)
6. [Disk — диск пользователя](#6-disk--диск-пользователя)

---

## 1. Обзор

Disk scope — доступ к Битрикс24 Диск: хранилища, папки, файлы.

Scope для прав: `disk`

---

## 2. Доступ к сервисам

```php
$diskScope = $b24->getDiskScope();

$storageService = $diskScope->storage();  // хранилища
$folderService  = $diskScope->folder();   // папки
$fileService    = $diskScope->file();     // файлы
$diskService    = $diskScope->disk();     // диск пользователя
```

---

## 3. Storage — хранилища

**Хранилище** — корневой контейнер для файлов. Бывает нескольких типов:
- Хранилище пользователя (личный диск)
- Хранилище группы/проекта
- Хранилище приложения

### Список хранилищ

```php
$storages = $diskScope->storage()->list(
    filter: [],
    start:  0
)->storages();

foreach ($storages as $storage) {
    echo $storage->id . ': ' . $storage->name . PHP_EOL;
    echo '  Тип: ' . $storage->entityType . PHP_EOL;
}
```

### Получить хранилище

```php
$storage = $diskScope->storage()->get($storageId)->storage();
echo $storage->id;
echo $storage->name;
echo $storage->rootObjectId;  // ID корневой папки
```

### Поля хранилища

```php
$fields = $diskScope->storage()->fields()->fields();
// Метаинформация о доступных полях хранилища
```

### Переименовать хранилище

```php
$diskScope->storage()->rename($storageId, 'Новое название');
```

### Типы хранилищ

```php
$types = $diskScope->storage()->getTypes()->types();
// user, group, project, network, ...
```

### Создать папку в хранилище

```php
$folderId = $diskScope->storage()->addFolder(
    storageId: $storageId,
    data: ['NAME' => 'Новая папка']
)->folder()->id;
```

### Дочерние элементы хранилища

```php
$children = $diskScope->storage()->getChildren(
    storageId: $storageId,
    filter: ['TYPE' => 'folder']  // folder или file
)->children();
```

### Загрузить файл в хранилище

Файл передаётся как **base64-строка**:

```php
$fileContent = file_get_contents('/path/to/document.pdf');
$base64      = base64_encode($fileContent);

$file = $diskScope->storage()->uploadFile(
    storageId:          $storageId,
    fileContentBase64:  $base64,
    data: [
        'NAME' => 'document.pdf',
    ],
    generateUniqueName: true,  // добавить суффикс при совпадении имён
    rights: []
)->file();

echo $file->id;
echo $file->downloadUrl;
```

### Хранилище приложения

Получить личное хранилище текущего приложения (создаётся автоматически):

```php
$storage = $diskScope->storage()->getForApp()->storage();
echo $storage->id;
```

---

## 4. Folder — папки

### Получить папку

```php
$folder = $diskScope->folder()->get($folderId)->folder();
echo $folder->id;
echo $folder->name;
echo $folder->storageId;
echo $folder->parentId;
```

### Создать вложенную папку

```php
$subFolderId = $diskScope->folder()->addSubFolder(
    folderId: $folderId,
    data: ['NAME' => 'Подпапка']
)->folder()->id;
```

### Переименовать папку

```php
$diskScope->folder()->rename($folderId, 'Новое название');
```

### Переместить папку

```php
$diskScope->folder()->moveTo(
    folderId:        $folderId,
    targetFolderId:  $targetFolderId
);
```

### Скопировать папку

```php
$diskScope->folder()->copyTo(
    folderId:        $folderId,
    targetFolderId:  $targetFolderId
);
```

### Удалить папку

```php
$diskScope->folder()->delete($folderId);
```

### Дочерние элементы папки

```php
$children = $diskScope->folder()->getChildren(
    folderId: $folderId,
    filter:   []
)->children();

foreach ($children as $item) {
    echo $item['TYPE'] . ': ' . $item['NAME'] . PHP_EOL;
}
```

### Загрузить файл в папку

```php
$fileContent = file_get_contents('/path/to/image.jpg');

$file = $diskScope->folder()->uploadFile(
    folderId:           $folderId,
    fileContentBase64:  base64_encode($fileContent),
    data: ['NAME' => 'image.jpg'],
    generateUniqueName: false
)->file();
```

---

## 5. File — файлы

### Получить файл

```php
$file = $diskScope->file()->get($fileId)->file();
echo $file->id;
echo $file->name;
echo $file->size;         // в байтах
echo $file->downloadUrl;
echo $file->storageId;
```

### Переименовать файл

```php
$diskScope->file()->rename($fileId, 'new_name.pdf');
```

### Переместить файл

```php
$diskScope->file()->moveTo(
    fileId:          $fileId,
    targetFolderId:  $targetFolderId
);
```

### Скопировать файл

```php
$diskScope->file()->copyTo(
    fileId:          $fileId,
    targetFolderId:  $targetFolderId
);
```

### Удалить файл

```php
$diskScope->file()->delete($fileId);
```

### Загрузить новую версию файла

```php
$newContent = file_get_contents('/path/to/updated_document.pdf');

$diskScope->file()->uploadVersion(
    fileId:            $fileId,
    fileContentBase64: base64_encode($newContent)
);
```

### Получить ссылку для скачивания

```php
$url = $diskScope->file()->getExternalLink($fileId)->url();
echo $url;  // публичная ссылка
```

---

## 6. Disk — диск пользователя

### Получить диск пользователя

```php
$disk = $diskScope->disk()->getStorage(
    userId: 1  // ID пользователя (1 = текущий)
)->storage();

echo $disk->id;
echo $disk->rootObjectId;  // ID корневой папки диска
```

### Паттерн: загрузить файл в папку и получить ссылку

```php
// 1. Получаем хранилище приложения
$storage = $diskScope->storage()->getForApp()->storage();

// 2. Создаём папку (или используем существующую)
$folder = $diskScope->storage()->addFolder(
    storageId: $storage->id,
    data: ['NAME' => 'uploads']
)->folder();

// 3. Загружаем файл
$file = $diskScope->folder()->uploadFile(
    folderId:           $folder->id,
    fileContentBase64:  base64_encode(file_get_contents('/tmp/report.pdf')),
    data: ['NAME' => 'report.pdf'],
    generateUniqueName: true
)->file();

// 4. Получаем публичную ссылку
$url = $diskScope->file()->getExternalLink($file->id)->url();
echo "Файл доступен по: $url";
```
