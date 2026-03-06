# Bitrix24 PHP SDK v3 — справочные файлы

Короткие шпаргалки для работы с SDK. Читать выборочно по задаче.

## Файлы

### [sdk-core.md](sdk-core.md)
Общее для всех scope: установка, авторизация (вебхук и OAuth), архитектура,
batch и пагинация, операторы фильтров, работа с результатами, получение сырых данных,
прямые вызовы `core->call()`, типичные ошибки.

### [filters-rest3.md](filters-rest3.md)
Типобезопасная фильтрация REST 3.0: принципы AND/OR, операторы, классы
`IntFieldConditionBuilder` / `StringFieldConditionBuilder` / `DateFieldConditionBuilder` / `BoolFieldConditionBuilder`,
примеры для всех типов полей, использование `setRaw()`, миграция с массивов.

### [scope-crm.md](scope-crm.md)
CRM scope: все доступные сервисы, сделки, контакты, лиды, компании, активности,
воронки, связи, товарные позиции, статусы, пользовательские поля, дубли, timeline,
типы Phone/Email в v3, массовые операции через batch.

### [scope-task.md](scope-task.md)
Task scope: задачи (CRUD), TaskItemBuilder, TaskFilter, batch-операции,
чеклисты, комментарии, учёт времени, результаты задач, стадии (Kanban),
пользовательские поля, справочник статусов и полей фильтра.

### [scope-im.md](scope-im.md)
im scope: системные и персональные уведомления, удаление, отметка как прочитанных,
подтверждение/ответ на уведомления с кнопками, вложения.

### [scope-imopenlines.md](scope-imopenlines.md)
imopenlines scope: управление открытыми линиями (Config), диалоги и сессии (Session),
действия оператора, отправка сообщений, CRM-чаты, чат-бот, внешние каналы (Network),
коннекторы (регистрация, сообщения, статусы), события открытых линий.

### [offline-events.md](offline-events.md)
Офлайн-события для синхронизации с внешними системами: почему нужен OAuth,
настройка локального приложения, event.bind, цикл синхронизации,
фильтрация по воронке, auth_connector, ONOFFLINEEVENT.

### [events-online.md](events-online.md)
Онлайн-события (webhook handler): RemoteEventsFactory, create(), validate() с проверкой подписи,
поддерживаемые типы событий (CRM, imopenlines, sonet_group, телефония, жизненный цикл),
EventLog — журнал аудита (get/list/tail), EventLogFilter, EventLogSelectBuilder, EventLogTailCursor.

### [scope-sonetgroup.md](scope-sonetgroup.md)
SonetGroup scope: группы и проекты социальной сети, два API-пространства
(`sonet_group.*` и `socialnetwork.api.workgroup.*`), CRUD, участники, владелец, события.

### [scope-lists.md](scope-lists.md)
Lists scope: универсальные списки (инфоблоки) — Lists, Field, Section, Element,
IBLOCK_TYPE_ID (`lists`, `lists_socnet`, `bitrix_processes`), int|string идентификаторы,
загрузка файлов, получение URL.

### [scope-disk.md](scope-disk.md)
Disk scope: Storage, Folder, File, Disk — CRUD, загрузка файлов через base64,
публичные ссылки, хранилище приложения, дочерние элементы.

## Быстрая навигация

| Задача | Файл | Раздел |
|---|---|---|
| Установить SDK | sdk-core.md | Установка |
| Подключиться через вебхук | sdk-core.md | Авторизация: вебхук |
| Подключиться через OAuth | sdk-core.md | Авторизация: OAuth |
| Фильтры и операторы | sdk-core.md | Фильтры |
| Получить сырой JSON | sdk-core.md | Сырые данные |
| SortOrder для REST 3.0 | sdk-core.md | SortOrder |
| SelectBuilder (выбор полей) | sdk-core.md | SelectBuilder |
| OAuth-исключения | sdk-core.md | OAuth-исключения |
| Работать со сделками | scope-crm.md | Сделки |
| Работать с контактами | scope-crm.md | Контакты |
| Работать с воронками | scope-crm.md | Воронки |
| Массово создать/обновить | scope-crm.md | Batch-операции |
| Работать с задачами | scope-task.md | Задачи |
| Фильтровать задачи | scope-task.md | TaskFilter |
| Добавить чеклист | scope-task.md | Чеклисты |
| Отправить уведомление | scope-im.md | Уведомления |
| Открытые линии (чат-поддержка) | scope-imopenlines.md | — |
| Внешний коннектор (Telegram и т.д.) | scope-imopenlines.md | Connector |
| Обработать событие открытой линии | scope-imopenlines.md | События |
| Настроить офлайн-события | offline-events.md | — |
| Обработать онлайн-событие | events-online.md | RemoteEventsFactory |
| Читать журнал аудита (EventLog) | events-online.md | EventLog |
| Работать с группами/проектами | scope-sonetgroup.md | — |
| Работать с универсальными списками | scope-lists.md | — |
| Загрузить файл на диск | scope-disk.md | Паттерн: загрузить файл |
| Читать файлы с диска | scope-disk.md | File |
