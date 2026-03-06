#!/bin/sh
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
IMAGE_NAME="b24-example"

# ── Загрузка переменных из .env ───────────────────────────────────────────────
if [ ! -f "$ENV_FILE" ]; then
    echo "ОШИБКА: файл .env не найден: $ENV_FILE"
    exit 1
fi

echo "Загружаем переменные из $ENV_FILE ..."

# Построчно читаем .env: пропускаем комментарии и пустые строки
while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        ''|'#'*) continue ;;
    esac
    export "$line"
done < "$ENV_FILE"

# ── Проверка обязательных переменных ──────────────────────────────────────────
PLACEHOLDER="https://your-portal.bitrix24.com/rest/1/your-token/"

if [ -z "$B24_WEBHOOK_URL" ] || [ "$B24_WEBHOOK_URL" = "$PLACEHOLDER" ]; then
    echo ""
    echo "ОШИБКА: укажите реальный URL вебхука в файле .env:"
    echo "  B24_WEBHOOK_URL=https://ваш-портал.bitrix24.ru/rest/1/токен/"
    echo ""
    echo "Как получить вебхук:"
    echo "  Битрикс24 → Разработчикам → Другое → Входящий вебхук"
    echo "  Выберите права: task, crm"
    echo ""
    exit 1
fi

# ── Сборка образа ─────────────────────────────────────────────────────────────
echo ""
echo "Собираем образ '${IMAGE_NAME}' ..."
docker build -t "$IMAGE_NAME" "$SCRIPT_DIR"

# ── Запуск контейнера ─────────────────────────────────────────────────────────
# --rm        — удалить контейнер после завершения
# --env-file  — Docker сам читает .env и передаёт переменные в контейнер.
#               Значения не попадают в историю shell и не видны в ps aux.
echo ""
echo "Запускаем контейнер ..."
echo ""
docker run --rm --env-file "$ENV_FILE" "$IMAGE_NAME"
