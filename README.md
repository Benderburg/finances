# Norocel 2 — этап A

React + strict TypeScript + Laravel Sanctum + MySQL 8.4. Новая версия находится в `frontend/` и `backend/`, ветка `norocel-2`. Исходная версия на Supabase сохранена в корне репозитория. Исходные ТЗ и аудит сохранены в `docs/reference/`.

Реализованы счета, полный журнал доходов/расходов/переводов/обменов, цели, долги, месячные бюджеты и повторения, отчёты, категории, профиль, регистрация/подтверждение/сброс пароля, административные профили, JSON preview/restore, PWA и добровольная офлайн-сводка. Денежные значения — строки целых minor units. Все финансовые команды выполняются в транзакции с блокировкой workspace, защитой от повторов и проверкой revision.

BNM reference rates и CSV относятся к этапу B. Для них добавлены интерфейсы `backend/app/Contracts/`; в интерфейсе пользователя нет неработающих кнопок.

## Локальный запуск

Нужны PHP 8.2 с PDO MySQL, BCMath, intl, mbstring, Composer, Node 22.14+ и Docker Desktop. Команды ниже выполняются в PowerShell из корня checkout. Docker содержит только тестовую базу; указанный пароль предназначен для localhost.

```powershell
docker compose up -d mysql
docker compose exec -T mysql mysql -uroot -plocal-norocel-only -e "CREATE DATABASE IF NOT EXISTS norocel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Set-Location backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
$env:NOROCEL_LOCAL_PASSWORD = 'local-testing-123'
php artisan norocel:local-user dev@norocel.test --name='Local Preview' --admin
Remove-Item Env:NOROCEL_LOCAL_PASSWORD
Set-Location ../frontend
npm ci
npm run build
Set-Location ../backend
php artisan serve --host=127.0.0.1 --port=8000
```

Открыть `http://127.0.0.1:8000`. Тестовый вход: `dev@norocel.test` / `local-testing-123`. Команда создания тестового пользователя запрещена в production. `.env` и зависимости исключены из Git. Не перезаписывайте существующий `.env` при повторном запуске и не меняйте существующий `APP_KEY`.

При разработке можно отдельно запустить `npm run dev` из `frontend/` и открыть `http://127.0.0.1:5173`: Vite проксирует `/api`, `/auth`, `/sanctum` на 8000. PWA проверяется на собранной версии через 8000. В dev письма записываются в `backend/storage/logs/laravel.log`; SMTP не используется. Для обычной регистрации откройте ссылку подтверждения из журнала. В production нужен работающий SMTP.

Локальная проверка deployment через PHP-FPM/Nginx после установки зависимостей и сборки:

```powershell
docker compose --profile web up -d --build
```

Открыть `http://127.0.0.1:8085`. Это локальная репетиция на той же тестовой базе. Профиль `web` монтирует checkout и требует заранее подготовленные `.env`, `vendor` и `public/build`; это не готовый production image. Процесс Node в runtime не нужен. Production-конфигурация описана в [RUNBOOK.md](RUNBOOK.md).

## Проверки

```powershell
Set-Location backend
php artisan test --compact
composer validate --no-check-publish
Set-Location ../frontend
npm run build
npm test
npx playwright install chromium
npm run test:e2e
```

Backend tests используют отдельную `norocel_test` на MySQL, очищают её и запускают concurrent case в отдельных процессах. Никогда не направляйте эту конфигурацию на рабочую базу. Для E2E нужен сервер на 8000, log mail и тестовый пользователь из инструкции. E2E создают только фиктивные данные `*.test`, читают локальные письма подтверждения/сброса, сохраняют screenshots/traces в `frontend/test-results/`. CI воспроизводит проверки в `.github/workflows/norocel-2.yml`.

## Миграция и приёмка

```powershell
Set-Location backend
php artisan norocel:migrate tests/fixtures/legacy-workspace.json --month=2026-10 --report=../migration-fixture-dry-run.json
# --apply разрешён только для local/test/staging. Укажите UUID существующего тестового пользователя:
php artisan norocel:migrate tests/fixtures/legacy-workspace.json --user=DESTINATION_UUID --month=2026-10 --apply --report=../migration-fixture-applied.json
```

Повтор того же source SHA-256 не создаёт дубли. Без `--apply` финансовые данные не изменяются. Для серверного экспорта нужен `--source-user=SOURCE_UUID`. Права и пароли не переносятся из пользовательского JSON. Отдельная CLI `norocel:identities` принимает только доверенный серверный файл; она сохраняет UUID, не переносит пароль Supabase и не отправляет письма. Подробности и расхождения — в [MIGRATION_REPORT.md](MIGRATION_REPORT.md).

Документы: [домен](NOROCEL_DOMAIN_SPEC.md), [API и JSON](API_CONTRACT.md), [QA и чеклист](RELEASE_CHECKLIST.md), [запуск, cutover, rollback](RUNBOOK.md). Реальный экспорт и доступ к целевому хостингу не предоставлены: проверка production-данных и конкретного хостинга остаётся условием выпуска. Физические Safari/iOS/Android устройства не проверялись.
