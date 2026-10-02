# Norocel 2 — приёмка этапа A

Дата локальной проверки: **2026-10-02**. Исходник: `Benderburg/finances`, `master@25d3a31`; рабочая ветка: `norocel-2`. Старое приложение сохранено. Все проверки и импорты выполнены на фиктивных пользователях и отдельных локальных MySQL-базах.

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
| `php artisan test --compact` | **28 passed, 239 assertions**; MySQL 8.4.11/InnoDB, отдельная `norocel_test` |
| `npm run build` | Успешно: strict TypeScript + Vite production build + PWA shell |
| `npm test` | **4 passed**: точные суммы, округление/границы, форматирование, справочник более 100 записей |
| `npx playwright test` | **10 passed**, 2 deployment cases пропущены намеренно: отдельный smoke ниже; desktop + mobile, итоговая сборка |
| PHP-FPM + Nginx smoke | **1 passed** на localhost:8085: `/up`, запрет `.env`, SW no-cache, CSRF/session login, API no-store, refresh `/reports` |
| Composer | Lockfile валиден, route cache собирается; `composer audit`: 0 advisories |
| npm | `npm ci` воспроизводим; `npm audit`: 0 vulnerabilities на дату проверки |
| Fixture migration | Dry-run → apply → replay; 3 операции, delta обоих счетов **0**, replay без дублей |

PHP CLI: 8.2.12; контейнер PHP-FPM: 8.2.34; Node: 22.14.0. Проверки зависят от lockfiles. Аудиты зависимостей отражают дату проверки, а не постоянную гарантию. GitHub Actions настроен, но удалённый CI в этой работе не запускался.

## Browser QA и практические пределы

Playwright/Chromium использует реальные HTTP/API, MySQL и session/CSRF, без подмены финансовых ответов. Desktop — 1440×1000, mobile — 390×844; отдельно проверено отсутствие горизонтального overflow на ширинах 360, 768 и 1440. Сценарии: регистрация/verification/reset, счета → income/expense → transfer/exchange → edit/void, goal spend/completion/void, settlement/void, бюджет и другой месяц, export/preview/restore/invalid JSON, logout/другой user, browser back, deep-link refresh, истёкшая сессия и потерянный ответ команды.

RO/RU/EN и light/dark/system проверены в браузере. HTML/script в описании отображается текстом. Итоговые dashboard-снимки сохранены в `docs/qa/`; dark RU reports и print reports находятся в `frontend/test-results/`, traces сохраняются при ошибке. Print QA проверяет CSS и rendered page, а не физический принтер. Денежные поля используют decimal input mode; safe-area CSS и нижняя навигация присутствуют, но экранная клавиатура и реальные safe areas устройства ещё требуют проверки.

Service worker действительно установлен и контролирует страницу; проверены offline reload shell, opt-in IndexedDB snapshot, отсутствие приватных API в Cache Storage, закрытые offline write actions и logout с последующим server logout. Обновление SW проверено через новую версию файла: активация ждёт подтверждения. Установка через системный диалог/Add to Home Screen, Safari/iOS и физические Android устройства **не проверены**. Offline draft находится только в памяти: reload закрывает форму; синхронизация финансовых записей не входит в A.

Ограничения импорта: 10 MiB, 50 000 операций, 100 000 строк документа. Проверены валидация и атомарность на fixtures; нагрузочный benchmark максимального документа на целевом сервере не выполнялся. Скорость Windows bind mounts локального Docker не является оценкой production latency.

## Перед выпуском на настоящий хостинг

- [x] Код A, migrations, CLI, API, UI, PWA, lockfiles и инструкции подготовлены.
- [x] Финансовая атомарность, ownership, retry/generation, MySQL concurrency и fixture reconciliation проверены.
- [x] Локальная репетиция PHP-FPM/Nginx выполнена; production Node process не требуется.
- [ ] Уточнить фактический provider, PHP/MySQL, document root, SSH/release и persistent storage; проверить конкретный staging-хост.
- [ ] Проверить HTTPS, Secure cookies, reverse proxy и SMTP verification/reset/email-change на согласованном тестовом адресе.
- [ ] Получить real server snapshot + real user JSON; сверить schema, UUID/roles, все суммы/валюты/связи и нулевые balance deltas каждого владельца.
- [ ] Проверить предупреждения/блокеры, повтор импорта, сохранение ID mapping и восстановление полной staging DB из backup.
- [ ] Проверить физический мобильный браузер, клавиатуру, safe areas и установку PWA; при необходимости провести benchmark максимального import.
- [ ] Владелец принимает проверяемый staging-результат и отдельно согласует cutover, freeze старых записей, rollback и onboarding.

Рабочая Supabase-база не читалась и не изменялась, реальные письма не отправлялись. Production migration/cutover, удаление старой версии и проверка конкретного хостинга **не выполнены**. Порядок дальнейших действий: `MIGRATION_REPORT.md` и `RUNBOOK.md`.

## Следующий инкремент B

BNM reference/historical rates, cross-currency conversion для отчётов/бюджетов и CSV import/export отложены до приёмки A, как требует ТЗ. Добавлены только расширяемые contracts; готовность этих функций не заявляется, неработающие кнопки не показываются.
