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

## Обновление этапа B — 2026-10-04

Развёрнут `norocel-2@6c912d004d4123085bb33edcd708d526ec1a52ea`. Применена только новая миграция `2026_10_04_000001_add_fx_and_csv`, затем пересобраны config/route caches. Приватный архив кода и сборки проверен по SHA-256 `a22a118d7936619ca75d4bc3f067bd2ae53de59e7427d7714963b113992c4521`. Сравнение контрольных сумм существующих таблиц и `.env` до/после подтвердило их неизменность; APP_KEY, аккаунт владельца и пароль сохранены. PHP 8.5.8 поддерживает необходимые curl/iconv/libxml/SimpleXML.

PWA version: `0d68442665d97d7d`, основной bundle: `index-C0d6ESMx.js`. Новая версия активирована штатной кнопкой обновления на пустой форме; сессия владельца сохранилась. Физические SW/manifest в public не восстановлены: URL по-прежнему обслуживает Laravel с no-cache. Старые hashed assets оставлены для безопасного перехода уже установленных PWA.

Загружена история BNM за **2026-05-01 — 2026-10-04**: 157 успешных дат, ноль `RATE_PROVIDER_UNAVAILABLE`. Проверен API курсов на первую дату: доступны MDL/EUR/USD/RON. В панели Timeweb создана и включена задача `Norocel BNM daily rates`, ежедневно **06:15, UTC+3**:

```sh
cd /home/c/ck85651/norocel/backend && /opt/php8.5/bin/php artisan norocel:fx-sync >> storage/logs/fx-sync.log 2>&1
```

Сам CLI определяет день в Europe/Chisinau. Timeweb запрещает запись через `crontab -`; расписание меняется в панели. История и ежедневный лог находятся в закрытом `backend/storage/logs`.

Production smoke check: `/up`, `/csv`, `/sw.js`, `/manifest.webmanifest` — 200; PWA-файлы имеют правильные MIME/no-cache. CSV без сессии — 401/no-store, с отдельной сессией владельца — 200, `text/csv`, attachment `norocel-journal.csv`, `no-store, private`, корректный version marker и набор колонок. Проверочная сессия завершена. Финансовые импорты на production не выполнялись; полный цикл CSV проверен на отдельной QA-базе.

Локально: 41 backend tests / 413 assertions, 4 frontend unit tests, 14 desktop/mobile E2E на PHP-FPM/Nginx. [CI для кода этапа B](https://github.com/Benderburg/finances/actions/runs/37197678143) успешно завершён на PHP 8.2 и 8.5. Процедура следующего инкрементального обновления: `scripts/deploy-stage-b-timeweb.sh`; контроль сохранности — `scripts/timeweb-stage-b-check.php`. Архив не включает `.env`, vendor, storage или существующие финансовые данные.

## Редизайн интерфейса — 2026-10-04

По запросу владельца опубликован код `dbb7c4b` из ветки `norocel-2`.
Архив `norocel-ui-redesign-86c15c8c18f588f1.zip` (223580 байт), SHA-256:
`742d3729b475e9dee6df65a4ed8db744bc3423243eece16587f125ba5c0a9c66`.
Он распакован через файловый менеджер Timeweb в
`/home/c/ck85651/norocel/backend` и содержит ровно десять файлов:
`public/build/index.html`, JS/CSS, пять локальных шрифтов Manrope,
`resources/pwa/sw.js` и `resources/pwa/manifest.webmanifest`.
Backend, `.env`, vendor, storage, база и расписание BNM не обновлялись;
миграции не запускались. Старые хешированные ресурсы оставлены для перехода PWA.

Новая PWA: `86c15c8c18f588f1`; bundle `index-CWJ4x05I.js`, CSS
`index-CqYSuUdM.css`. Каждый из десяти опубликованных файлов скачан и
сравнен по SHA-256 с локальной сборкой — все совпадают. SW и manifest
сохранили `public, no-cache` и правильные MIME. `/`, `/up`, `/csv`,
`/savings`, `/reports` и прежний JS — HTTP 200; `.env` — 403;
API dashboard без сессии — 401. В браузере проверены вход владельца,
главная, накопления, отчёты и принятие новой PWA. Финансовые записи
для проверки на production не создавались.
Timeweb предупреждал о DDoS и перебоях: один HTTP-запрос оборвался,
а первый запрос денежного потока показал ошибку сервера; повторные
запросы и загрузка отчёта прошли успешно.

Для отката только интерфейса сохранён локальный архив
`.runtime/norocel-ui-rollback-0d68442665d97d7d.zip` с предыдущими
`index.html`, SW и manifest, скачанными до обновления. Его следует
распаковать в тот же backend; старые assets уже доступны на сервере.
Данные и `.env` при таком откате не затрагиваются.
