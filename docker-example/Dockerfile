# ── Этап 1: установка зависимостей через Composer ────────────────────────────
FROM composer:2 AS composer-stage

WORKDIR /app

COPY composer.json ./

# --ignore-platform-reqs: образ composer может иметь другую конфигурацию PHP,
# чем целевой образ — разрешаем несоответствие и устанавливаем зависимости
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ── Этап 2: финальный образ ───────────────────────────────────────────────────
FROM php:8.4-cli-alpine

# SDK v3 требует:
#   ext-curl  — HTTP-клиент Symfony (curl-dev нужен только при сборке)
#   ext-intl  — Carbon, форматирование дат (icu-libs нужны при запуске)
#   ext-json  — встроен в PHP 8+, устанавливать не нужно
#
# Стратегия: ставим *-dev для сборки расширений, потом удаляем заголовочные
# файлы; runtime-библиотеки (libcurl, icu-libs) явно оставляем.
RUN apk add --no-cache \
        curl-dev \
        icu-dev \
        icu-libs \
    && docker-php-ext-install \
        curl \
        intl \
    && apk del \
        curl-dev \
        icu-dev \
    && rm -rf /tmp/*

WORKDIR /app

# Копируем установленные зависимости из этапа сборки (vendor от Composer)
COPY --from=composer-stage /app/vendor ./vendor

# Копируем скрипт примера
COPY example.php ./

# Переменные с пустыми значениями по умолчанию — скрипт явно проверит их
ENV B24_WEBHOOK_URL="" \
    B24_RESPONSIBLE_ID="0" \
    B24_DATE_FROM="2025-01-01"

CMD ["php", "example.php"]
