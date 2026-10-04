# Norocel 2 — приёмка этапа A

Дата проверки и выпуска: **2026-10-03**. Исходник: `Benderburg/finances`, `master@25d3a31`; рабочая ветка: `norocel-2`. Старый код сохранён в Git. Автоматические проверки и импорты выполнены на фиктивных пользователях и отдельных локальных MySQL-базах. Новая версия развёрнута на **https://norocel.noros.net/** чистой установкой, как разрешил пользователь.

## Реализовано и проверено

| Область | Результат |
|---|---|
| Денежное ядро | Строковые minor units; точные курсы/half-up; одна операция на перевод или обмен; проверка всех затронутых остатков при create/edit/void |
| Атомарность и повторы | Транзакция, блокировка workspace, revision, ключ команды и payload hash; tombstones после restore; реальная гонка двух отдельных PHP-процессов |
| Счета и журнал | Regular/savings, история, архивирование, поиск и страницы журнала, детали, редактирование, void и audit |
| Цели | Отдельный savings-счёт, saved/spent/funded, целевой расход и completion marker, отмена расхода/завершения, cancel/resume |
| Обязательства | Receivable/payable/credit; полное погашение одной связанной операцией; атомарный отказ и отмена погашения |
| Бюджеты | Месяц и фиксированная валюта, превышение, расходы других валют отдельно; повторения и disabled overrides; архив категории останавливает будущие шаблоны |
| Отчёты | Balances отдельно от cash flow, три валюты отдельно; месячный cash flow, расходы по категориям, динамика остатков и печатный вид |
| Категории и профиль | Системные RO/RU/EN и собственные названия, архивирование, язык/тема/base currency/timezone; финансовые суммы от настроек не меняются |
| Авторизация | Регистрация, подтверждение email, вход/выход, реальный reset password с новым входом, истёкший token, изменение email/пароля |
| Права | Чужие счета/категории/цели/долги недоступны; admin не обходит ownership; публичные профиль и backup не принимают роли/пароли |
| JSON v2 | Полный export, строгий preview, stale/expired checks, prior backup, атомарный restore; инъекция ошибки посреди insert даёт полный rollback |
| Миграция | Legacy и trusted server adapters, exact JSON, dry run/apply/replay, сверка остатков; отдельный trusted identity import без отправки писем |
| React/PWA | Strict TypeScript, responsive UI, три языка/темы, installable manifest/icons, shell cache и подтверждение обновления; добровольная минимальная offline-сводка |
| Потеря сети/сессии | Повтор с тем же ключом после потерянного ответа; форма сохраняется в памяти при отключении; запись offline закрыта; logout/401 очищают private cache и UI |

Сценарии финансовой целостности проверены в `backend/tests/Feature/FinanceTest.php`, миграции — `MigrationTest.php`, независимая гонка — `ConcurrencyTest.php`, авторизация — `AuthTest.php`. Форматы и ограничения описаны в `API_CONTRACT.md` и `NOROCEL_DOMAIN_SPEC.md`.

## Результаты локальных проверок

| Проверка | Результат |
|---|---|
| `php vendor/bin/phpunit -c phpunit.timeweb.xml` | **31 passed, 268 assertions**; PHP 8.5.11, MySQL 8.4.11/InnoDB, отдельная `norocel_test`; включает bootstrap владельца и публичные PWA routes |
| `npm run build` | Успешно: strict TypeScript + Vite production build + PWA shell |
| `npm test` | **4 passed**: точные суммы, округление/границы, форматирование, справочник более 100 записей |
| `NOROCEL_QA_URL=http://127.0.0.1:8001`, deployment QA | **12 passed**, без пропусков; desktop + mobile, PHP 8.5/FPM + Nginx, полный auth/finance/offline/update цикл |
| Production на Timeweb | PHP 8.5.8, Percona/MySQL 8.4.11; HTTPS, `/up`, login/logout и `/admin`, deep-link reload, Secure/HttpOnly session, `.env` 403, API 401/no-store, SW и manifest 200/no-cache |
| Composer | Lockfile валиден, route cache собирается; `composer audit`: 0 advisories |
| npm | `npm ci` воспроизводим; `npm audit`: 0 vulnerabilities на дату проверки |
| Fixture migration | Dry-run → apply → replay; 3 операции, delta обоих счетов **0**, replay без дублей |

Первичная проверка 2026-10-02 использовала PHP CLI 8.2.12 и FPM 8.2.34; повторная полная проверка 2026-10-03 — PHP 8.5.11/FPM и MySQL 8.4.11. Production CLI и web PHP — 8.5.8. Node: 22.14.0. Проверки зависят от lockfiles. Аудиты зависимостей отражают дату 2026-10-02, а не постоянную гарантию. GitHub Actions запущен для PHP 8.2/8.5: [результаты CI](https://github.com/Benderburg/finances/actions/workflows/norocel-2.yml).

Первая проверка GitHub выявила накопление лимитов авторизации между быстрыми тестами и задержку удаления указателя offline snapshot при выходе. Указатель теперь удаляется до асинхронного открытия IndexedDB, что также исключает удаление указателя следующего пользователя поздним завершением очистки. CI очищает локальный файловый cache перед каждым браузерным сценарием: fixture требует loopback origin и `APP_ENV=local`. Лимиты production не меняются. Отдельные Nginx/FPM-сценарии в CI с `artisan serve` пропускаются; локальная полная проверка на FPM включает все 12.

## Browser QA и практические пределы

Playwright/Chromium использует реальные HTTP/API, MySQL и session/CSRF, без подмены финансовых ответов. Desktop — 1440×1000, mobile — 390×844; отдельно проверено отсутствие горизонтального overflow на ширинах 360, 768 и 1440. Сценарии: регистрация/verification/reset, счета → income/expense → transfer/exchange → edit/void, goal spend/completion/void, settlement/void, бюджет и другой месяц, export/preview/restore/invalid JSON, logout/другой user, browser back, deep-link refresh, истёкшая сессия и потерянный ответ команды.

RO/RU/EN и light/dark/system проверены в браузере. HTML/script в описании отображается текстом. Итоговые dashboard-снимки сохранены в `docs/qa/`; dark RU reports и print reports находятся в `frontend/test-results/`, traces сохраняются при ошибке. Print QA проверяет CSS и rendered page, а не физический принтер. Денежные поля используют decimal input mode; safe-area CSS и нижняя навигация присутствуют, но экранная клавиатура и реальные safe areas устройства ещё требуют проверки.

Service worker действительно установлен и контролирует страницу; проверены offline reload shell, opt-in IndexedDB snapshot, отсутствие приватных API в Cache Storage, закрытые offline write actions и logout с последующим server logout. Обновление SW проверено через новую версию файла: активация ждёт подтверждения. Установка через системный диалог/Add to Home Screen, Safari/iOS и физические Android устройства **не проверены**. Offline draft находится только в памяти: reload закрывает форму; синхронизация финансовых записей не входит в A.

Ограничения импорта: 10 MiB, 50 000 операций, 100 000 строк документа. Проверены валидация и атомарность на fixtures; нагрузочный benchmark максимального документа на целевом сервере не выполнялся. Скорость Windows bind mounts локального Docker не является оценкой production latency.

## Выпуск на Timeweb и оставшиеся проверки

- [x] Код A, migrations, CLI, API, UI, PWA, lockfiles и инструкции подготовлены.
- [x] Финансовая атомарность, ownership, retry/generation, MySQL concurrency и fixture reconciliation проверены.
- [x] Локальная репетиция PHP-FPM/Nginx выполнена; production Node process не требуется.
- [x] Подтверждены Timeweb shared hosting, PHP 8.5.8, Percona/MySQL 8.4.11, отдельная `ck85651_norocel`, закрытый backend и `public_html` → `backend/public`.
- [x] Проверены HTTPS, Secure/HttpOnly/SameSite session, API no-store, защита `.env`, вход/выход и админка владельца `fritz@noros.net`.
- [x] Исправлена годовая кешируемость статических PWA-файлов Timeweb: Laravel routes обслуживают SW/manifest без сессионных cookies, с правильным MIME и no-cache.
- [x] По разрешению пользователя выполнена чистая установка без переноса старых данных и исходного backup. Старый document root и временные приватные архивы удалены после проверки. Соседний `noros.net` отвечает HTTP 200.
- [ ] Проверить доставку verification/reset/email-change на согласованном адресе: настроен Exim/sendmail, реальные письма не отправлялись.
- [ ] Для будущего переноса сохранённых данных выполнить реальную сверку и recovery по `MIGRATION_REPORT.md`; текущий выпуск использует согласованную пустую базу.
- [ ] Проверить физический мобильный браузер, клавиатуру, safe areas и установку PWA; при необходимости провести benchmark максимального import.
- [x] Пользователь отдельно разрешил замену прототипа на Timeweb и указал email первого администратора; создание выполнено без рассылки onboarding.

Рабочая Supabase-база не читалась и не изменялась. На новом сайте один подтверждённый администратор, Main account с нулевым остатком и 15 системных категорий; старые Supabase-пароли и сессии не переносятся. Пароли и APP_KEY находятся вне Git. Процедура следующего выпуска: `docs/TIMEWEB_DEPLOYMENT.md` и `RUNBOOK.md`; историческая репетиция миграции — `MIGRATION_REPORT.md`.

## Инкремент B — 2026-10-04

После приёмки A добавлены RON, версионные BNM reference/historical rates, cross-currency valuation для dashboard/reports/budgets, indicative quote, CSV export с обратимой защитой Excel и атомарный additive import с preview/mapping/duplicate confirmation. Серверная проверка PHP 8.5: **41 tests / 413 assertions**, frontend unit: **4 passed**, production build успешен. Полный E2E на PHP-FPM/Nginx: **14 passed**, desktop/mobile, включая CSV и RON. Детали и практические пределы — [Stage B](docs/STAGE_B.md). Обновление использует отдельную миграцию и сохраняет существующие workspace/env/APP_KEY.
