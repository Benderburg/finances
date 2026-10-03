# Timeweb: Norocel 2

2026-10-03 пользователь разрешил заменить прототип чистой установкой без переноса данных и резервной копии исходного сайта. Соседний `noros.net` не входит в эту операцию. Старый код остаётся в истории Git; Supabase в новой версии не используется.

Подтверждено в панели и SSH: виртуальный хостинг, сайт `norocel.noros.net`, каталог `/home/c/ck85651/norocel`, PHP CLI `/opt/php8.5/bin/php` (8.5.8), Percona/MySQL 8.4.11. Новая отдельная база и её пользователь: `ck85651_norocel`, доступ `localhost`. Админка встроена по `/admin`.

Установка завершена 2026-10-03: применены четыре миграции, создан подтверждённый администратор `fritz@noros.net`, язык RU, один Main account с нулевым остатком, 15 категорий и ноль операций. Собраны config/route/view caches. Проверены вход, выход, повторный вход и обновление `/admin`/`/reports`; `/up` и PWA-файлы отвечают 200, `.env` — 403, API без сессии — 401/no-store, сессионная cookie — Secure/HttpOnly/SameSite=Lax. SW и manifest — no-cache с корректным MIME и scope `/`. Старый document root, bootstrap secret и три временных архива удалены после проверки канонических путей. Соседний `noros.net` отвечает 200. Пароли не входят в Git; они передаются владельцу локальным приватным файлом. Снимок результата: `docs/qa/timeweb-live.png`.

## Сборка и установка

Собрать frontend через `npm ci && npm run build`. В отдельной папке установить backend из `composer.lock`: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`. Включить собранные `public/build`, `public/sw.js`, manifest и icons. Не включать тестовые данные, логи и сессии.

Production `.env` заполнить по `backend/.env.example.timeweb`. Создать APP_KEY один раз, хранить между выпусками. DB_PASSWORD и пароль первого администратора хранить вне Git. Приватный архив с `.env` загружать только в каталог сайта вне `public_html`; проверить SHA-256, распаковать в `backend`, затем удалить архив. Права `.env`: 600; writable-каталоги: `storage` и `bootstrap/cache`.

В каталоге `backend`:

```sh
/opt/php8.5/bin/php artisan migrate --force
/opt/php8.5/bin/php artisan norocel:owner OWNER_EMAIL --name=Fritz --locale=ru --password-file=storage/app/private/owner-init.secret
/opt/php8.5/bin/php artisan config:cache
/opt/php8.5/bin/php artisan route:cache
/opt/php8.5/bin/php artisan view:cache
```

Команда `norocel:owner` работает с пустой базой, создаёт подтверждённого администратора, Main account и системные категории без отправки писем. Повтор с тем же владельцем сохраняет пароль; другие существующие пользователи блокируют инициализацию. После успеха удалить единственный файл `owner-init.secret`.

В настройках только сайта `norocel` выбрать web PHP 8.5. CLI выбирается отдельно. `public_html` должен ссылаться только на `backend/public`. Перед заменой проверить канонические пути; не трогать соседние сайты. Пользователь разрешил удаление старого document root без бекапа.

Timeweb отдаёт существующие статические файлы с `max-age=31536000`. После сборки PWA удалить только `public/sw.js` и `public/manifest.webmanifest`: build script сохраняет вторые копии в `resources/pwa`, а Laravel обслуживает прежние URL с `Cache-Control: no-cache`, правильным MIME и областью `/`. Hashed assets и icons остаются статическими. Пересобрать route cache после изменения routes. Настройки `.htaccess` не влияют на статику Nginx: [документация Timeweb](https://timeweb.com/ru/docs/virtualnyj-hosting/obshchaya-informaciya-o-hostinge/osobennosti-tekhnicheskogo-resheniya/).

Проверить HTTPS, `/up`, login/logout, `/admin`, deep-link reload, Secure/HttpOnly cookies, запрет доступа к `.env`/vendor/storage, no-store для API и обновление PWA. Почта настроена на Exim через `sendmail -bs -i`; sender должен существовать на своём домене. Доставка реальных писем отдельно от проверки приложения; запуск не подтверждает доставку. Документация: [Laravel на Timeweb](https://timeweb.com/ru/docs/virtualnyj-hosting/prilozheniya-i-frejmvorki/laravel/), [PHP](https://timeweb.com/ru/docs/virtualnyj-hosting/php/izmenenie-versii-php/), [почта](https://timeweb.com/ru/docs/pochta/osnovnye-voprosy-po-rabote-s-pochtoj/otpravka-pochty-cherez-skripty/).

## Изолированная проверка PHP 8.5

QA использует отдельный Compose project, временную MySQL 8.4 и порт 8001. На Windows зависимости копируются в Linux-том для нормальной скорости PHP-FPM. Перед первым запуском создать `.runtime/timeweb-qa-vendor.zip` из `backend/vendor` через `System.IO.Compression.ZipFile.CreateFromDirectory` (без родительской папки в архиве).

```powershell
docker compose -p norocel-timeweb-qa -f compose.timeweb-qa.yaml up -d --build --wait
docker compose -p norocel-timeweb-qa -f compose.timeweb-qa.yaml exec -T mysql-timeweb mysql -uroot -plocal-norocel-only -e 'CREATE DATABASE IF NOT EXISTS norocel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
docker compose -p norocel-timeweb-qa -f compose.timeweb-qa.yaml exec -T php-timeweb php vendor/bin/phpunit -c phpunit.timeweb.xml
docker compose -p norocel-timeweb-qa -f compose.timeweb-qa.yaml exec -T php-timeweb php artisan migrate --force
docker compose -p norocel-timeweb-qa -f compose.timeweb-qa.yaml exec -T -e NOROCEL_LOCAL_PASSWORD=local-testing-123 php-timeweb php artisan norocel:local-user dev@norocel.test --name='Local Preview' --admin
$env:NOROCEL_QA_URL='http://127.0.0.1:8001'
$env:NOROCEL_DEPLOYMENT_QA='1'
$env:NOROCEL_E2E_RESET_CACHE='1'
Set-Location frontend
npm run test:e2e
```

PHPUnit переопределяет `$_ENV` и `$_SERVER`; TestCase до очистки данных требует `APP_ENV=testing` и базу `norocel_test`. Тесты не запускаются на Timeweb. QA-пароль предназначен только для локальной базы. CI проверяет PHP 8.2 и 8.5. `NOROCEL_E2E_RESET_CACHE=1` очищает локальный cache перед каждым браузерным сценарием через native PHP, чтобы быстрые тесты не накапливали общие auth limits. Fixture допускает только `127.0.0.1` и `.env` с `APP_ENV=local`; production limits остаются прежними.
