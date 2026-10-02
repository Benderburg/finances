# Функциональная ревизия Norocel

**Дата ревизии:** 2 октября 2026  
**Объект:** текущий `master` (`25d3a31`)  
**Метод:** статический анализ HTML/CSS/JavaScript, SQL-схемы и миграций; проверка синтаксиса JavaScript через `node --check`. Живая Supabase-база не изменялась, браузерный E2E под авторизованным пользователем не выполнялся.

## 1. Краткий вывод

Norocel — SPA на vanilla JavaScript. `index.html` содержит все экраны и модальные формы; `app.js` содержит обработчики и сценарии; `ui.js` — рендеринг; `store.js` — in-memory state и вычисления; `supabase-api.js` — доступ к данным. Backend-логика ограничена Supabase Auth, RLS, PostgreSQL constraints и trigger functions.

Сильная сторона текущей модели: балансы вычисляются из операций, а перевод/обмен хранится одной строкой и атомарно изменяет два счёта. Основная слабость: некоторые составные действия не атомарны, а ряд инвариантов проверяется только при `INSERT/UPDATE`, но не при удалении или параллельных запросах.

Общая оценка: основные доходы, расходы, счета, переводы, цели, обязательства и RLS **работают**, но мультивалютная аналитика, управление regular-счетами, lifecycle целей, импорт, reset password и целостность составных операций — **частично**.

### Легенда статусов

- `работает` — доступный сценарий реализован от UI до БД без существенного разрыва;
- `частично` — основной путь есть, но имеются значимые пробелы или нарушения целостности;
- `заглушка` — элемент/ветка есть, но полезного действия не выполняет;
- `не используется` — код/поле существует, но нет доступного UI-пути или потребителя.

## 2. Пользовательские экраны и возможности

| Экран / функция | Статус | Фактическое поведение |
|---|---|---|
| Вход email/password | `работает` | Supabase `signInWithPassword`, сессия восстанавливается SDK. |
| Регистрация | `работает` | Supabase `signUp`; профиль создаётся не auth-trigger-ом, а при первом получении сессии в приложении. |
| Сброс пароля | `частично` | Письмо отправляется, но нет recovery-экрана и вызова `updateUser({password})`; redirect ведёт на origin обычного приложения. |
| Dashboard | `частично` | Показывает доход, расход, net flow, среднее, savings rate, два графика и последние операции за выбранный месяц и только в base currency. Поле «баланс» фактически равно `income - expense`, а не сумме балансов счетов. |
| Список операций | `частично` | Видны только `income`/`expense`; есть фильтры и редактирование. `transfer`/`exchange` из этого экрана исключены. |
| Добавление дохода/расхода | `работает` | Выбор счёта, суммы, даты, категории и описания; double-submit блокируется. Расход сервером ограничен текущим балансом. |
| Бюджеты по категориям | `частично` | CRUD работает, план/факт считаются за текущий выбранный месяц. У бюджета нет валюты и периода: одно число на категорию применяется ко всем месяцам и меняет смысл при смене base currency. Hero-кнопка открывает budget form, но подписана ключом «добавить категорию». |
| «Накопления» / счета | `частично` | Создание, перевод, списание, история и баланс есть. Карточками показаны только `savings`, но итог «всего накоплено» включает и regular-счета с `include_in_total=true`. |
| Создание regular-счёта | `частично` | Тип есть в форме и в БД, но после создания regular-счёт не попадает в сетку счетов, поэтому нет обычного UI-пути к его истории, редактированию и удалению. |
| Перевод/обмен между счетами | `работает` | Одна строка `transactions` с двумя суммами; баланс исходного уменьшается, целевого — увеличивается. Детали и ограничения — в разделе 5. |
| Цели | `частично` | CRUD, валюта, deadline, связь 1:1 с savings-счётом, прогресс и «потратить» есть. Статус `reached` часто лишь вычислен в UI, `cancelled` нельзя выставить из UI, а списание+смена статуса не атомарны. |
| Обязательства: долги, дебиторка, кредиты | `частично` | CRUD открытых записей, итоги по валютам и погашение есть. Финансовая операция и перевод обязательства в `settled` выполняются двумя запросами без транзакции. |
| Отчёты | `частично` | 6-месячный income/expense, all-time структура расходов, all-time net-flow и top categories. Всё только в base currency; график «баланс» игнорирует opening balances и transfers. |
| Печать отчётов | `работает` | Вызывает `window.print()`. |
| Настройки профиля | `частично` | Имя, email, avatar URL сохраняются в Auth metadata и `profiles` двумя запросами. При смене email профиль может обновиться раньше подтверждения email в Auth. |
| Язык RO/RU/EN | `работает` | Локально и в `profiles.language`; есть локализация чисел, дат и категорий. |
| Тема light/dark/system | `работает` | Хранится только локально; system реагирует на изменение OS theme. |
| Базовая валюта MDL/EUR/USD | `частично` | Переключает выборку аналитики, но не конвертирует суммы и не переводит счета в новую валюту. |
| JSON backup/export | `работает` | Выгружает profile, accounts, categories, transactions, budgets, goals, liabilities. |
| JSON import | `частично` | Полная замена данных без транзакции и предварительной валидации; ошибки фазы удаления не проверяются, а ошибка вставки оставляет частично импортированное состояние. |
| CSV import/export | `не используется` | CSV-кода, кнопок и формата в проекте нет. |
| Admin dashboard | `работает` | Отдельный route `/admin`/`?admin=1`, счётчики users/retention/premium, список профилей, изменение имени, billing и admin flag. Доступ до чужих финансов админу не даётся. |
| Удаление admin-пользователя | `заглушка` | В click-handler есть пустая ветка `admin-user-delete-button`, но кнопки и delete-вызова нет. |

## 3. Сущности данных и связи

### `auth.users` (Supabase Auth)

Владелец всех пользовательских данных. При удалении auth user все таблицы с `user_id` удаляются по `ON DELETE CASCADE`.

### `profiles`

- PK/FK `id -> auth.users.id`;
- `email`, `full_name`, `avatar_url`;
- `language` (`ro|ru|en`), `currency` (`MDL|EUR|USD`);
- `billing` (`regular|premium`), `is_admin`, `last_seen_at`;
- timestamps.

Статус: `работает`. `last_seen_at` обновляется при каждом `saveProfile` после получения сессии.

### `accounts`

- принадлежит user;
- `type`: `regular|savings`;
- фиксированная `currency_code`;
- `opening_balance >= 0`;
- `include_in_total`;
- timestamps.

Связи: счёт может участвовать во многих transactions; savings-счёт может быть связан максимум с одной goal. Статус: `работает` в БД, `частично` в UI.

### `categories`

- пользовательские categories с `key`, `name`, `type=income|expense`;
- unique `(user_id,type,key)` и case-insensitive unique name внутри типа;
- не связаны FK ни с `transactions.category`, ни с `budgets.category`.

Дефолтные categories вообще не строки БД, а массивы в `i18n.js`. Статус: `частично` из-за текстовых ссылок и поведения при удалении.

### `transactions`

Единая таблица для income, expense, transfer и exchange:

- для income/expense: `account_id`, `amount`, `currency_code`;
- для transfer/exchange: `from_account_id`, `to_account_id`, `amount`, `currency_code`, `converted_amount`, `converted_currency_code`, `exchange_rate`;
- общие: `description`, `transaction_date`, текстовая `category`, optional `goal_id`, timestamps.

`transactions_shape_check` делает формы операций взаимоисключающими. Статус: `работает`.

### `goals`

- `target_amount`, legacy `saved_amount`, currency, optional savings account;
- `status`: `active|reached|spent|cancelled`;
- icon, deadline, completed_at, timestamps;
- unique `savings_account_id` обеспечивает связь account 0..1 <-> goal 0..1.

`saved_amount` используется только у legacy/непривязанных целей; UI не даёт его менять. Статус: `частично`.

### `budgets`

- `(user_id, category)` unique;
- один `monthly_limit > 0`;
- нет currency, month/year, category FK.

Статус: `частично`.

### `liabilities`

- counterparty, amount, currency;
- `liability_type`: `receivable|payable|credit`;
- due date, comment;
- `status=open|settled`;
- optional `settlement_account_id`, `settlement_transaction_id`, `settled_at`.

Статус: `частично`: связи валидируют владение и валюту, но не проверяют, что settlement transaction имеет нужный тип, сумму и тот же account.

### Схема связей

```text
auth.users 1──1 profiles
     │
     ├──< accounts 1──< transactions >──1 goals (optional goal_id)
     │       ├──< outgoing transfer/exchange
     │       ├──< incoming transfer/exchange
     │       └──0..1 goals (unique savings_account_id)
     ├──< categories  ..text key.. transactions.category
     │                    `..text key.. budgets.category
     ├──< budgets
     ├──< goals
     └──< liabilities >──0..1 accounts/transactions (settlement)
```

## 4. Типы финансовых операций

| Тип | Статус | Влияние |
|---|---|---|
| `income` | `работает` | `account balance += amount`. |
| `expense` | `работает` | `account balance -= amount`; при insert/update проверяется достаточность средств. |
| `transfer` | `работает` | `from -= amount`, `to += converted_amount`; UI выбирает тип для одинаковых валют. |
| `exchange` | `работает` | То же влияние, UI выбирает тип для разных валют. |

Отдельных типов adjustment, refund, fee, opening balance transaction, goal contribution, debt issue/repayment нет. Начальный остаток — поле account, а погашение долга порождает обычный income/expense.

## 5. Балансы и переводы

### Расчёт баланса

И клиентский `getAccountBalance`, и SQL `get_account_balance` считают:

```text
balance(account) = opening_balance
                 + Σ income.amount
                 - Σ expense.amount
                 - Σ outgoing transfer/exchange.amount
                 + Σ incoming transfer/exchange.converted_amount
```

Материализованного `current_balance` нет. Статус: `работает`. Расхождение между клиентом и SQL в обычном потоке не обнаружено.

Важно: dashboard/report «баланс» эту формулу **не** использует; там считается чистый income/expense flow.

### Перевод в одной валюте

Статус: `работает`.

- Сохраняется **одна** `transactions`-строка, а не две проводки.
- `type=transfer`, `account_id=NULL`, есть from/to accounts.
- `amount == converted_amount` проверяется и UI, и trigger-ом.
- Source и target должны быть разными и принадлежать user.
- До вставки trigger проверяет баланс source account.

### Перевод между разными валютами

Статус: `частично` из-за неполной валидации курса и отсутствия последующего UI-аудита.

Хранятся:

- `from_account_id`;
- `to_account_id`;
- `amount` — сколько списано;
- `currency_code` — валюта source account;
- `converted_amount` — сколько зачислено;
- `converted_currency_code` — валюта target account;
- `exchange_rate`;
- `transaction_date`, `description`, timestamps.

Конвенция курса в SQL:

```text
exchange_rate = amount / converted_amount
```

То есть курс — «единиц source currency за одну единицу target currency». Пример: 1800 MDL -> 100 USD даёт 18 MDL/USD. Если UI не передал курс, trigger вычислит его с точностью 8 знаков. Если user ввёл курс вручную, он сохраняется как есть.

Ограничения:

- UI всегда требует обе суммы; вариант «сумма + курс и авторасчёт второй суммы» не реализован;
- ручной `exchange_rate` не сверяется с `amount / converted_amount`;
- БД не запрещает `type=transfer` для разных валют и `type=exchange` для одинаковых; корректный тип выбирает только UI;
- курс и комментарий не показываются после сохранения;
- нет fee/spread/provider/source-of-rate;
- нет внешнего FX API и нет переоценки истории.

Атомарность самого перевода: `работает`, потому что обе стороны — одна строка и один `INSERT`.

## 6. Редактирование и удаление

### Income/expense

- Редактирование: `работает`. Строка обновляется in place, поэтому баланс автоматически пересчитывается. При update expense SQL исключает старую строку из available balance.
- Удаление: `работает`. Запись удаляется после confirm, баланс пересчитывается.
- Пробел: update/delete income не перевалидирует уже существующие расходы. Можно уменьшить/удалить старый income и получить отрицательный итоговый баланс, несмотря на запрет overdraft при создании expense.

### Transfer/exchange

- Редактирование: `не используется` в UI. API может обновить такую строку, но из интерфейса он не вызывается.
- Удаление: `не используется` в доступном UI. Общий delete-handler и delete-кнопка в markup для transfer написаны, но трансферы не попадают в список с `withActions=true`, а в account history actions отключены.
- Если удалить transfer через API, обе стороны исчезнут атомарно. Но баланс target account может стать отрицательным, если зачисление уже было потрачено.

### Goal expense

- Редактирование: `не используется`; edit-кнопка скрыта для transaction с `goal_id`.
- Удаление: `частично`; expense удаляется, но stored goal status/дата завершения не откатываются. Если цель уже имеет stored `spent`, computed status так и останется `spent`.

### Liability settlement transaction

- Обычный transaction list не знает, что income/expense связан с liability, поэтому его можно редактировать как обычный.
- При удалении transaction FK ставит `settlement_transaction_id=NULL`, но liability остаётся `settled` с `settled_at`.
- При удалении liability связанный income/expense остаётся и продолжает влиять на баланс.

### Account

- Opening balance после создания отключён в UI и сохраняется старым значением.
- Name, type, currency, include-in-total можно менять. Изменение currency account не перевалидирует старые transactions; изменение linked savings account на `regular` не перевалидирует goal.
- FK операций формально `ON DELETE SET NULL`, но shape-check требует account links. Поэтому удаление счёта, который участвует в transactions, должно завершиться ошибкой constraint; UI не объясняет причину.

### Goal/category

- Удаление goal ставит `transactions.goal_id=NULL`; сам expense остаётся, категория `goal_expense` остаётся.
- Rename custom category не меняет immutable key, поэтому ссылки продолжают отображать новое name — `работает`.
- Delete custom category не меняет transactions/budgets. В истории будет виден raw key, а budget останется существовать — `частично`.

## 7. Категории

Статус системы: `частично`.

Дефолтные income categories: `salary`, `freelance`, `investments`, `gifts`, `other_income`.  
Дефолтные expense categories: `food`, `transport`, `housing`, `entertainment`, `shopping`, `health`, `education`, `utilities`, `other_expense`.

Они зашиты в client, локализуются и не имеют DB id. Custom categories хранятся в `categories`; key формируется как `custom_<slug>`, имя можно менять, тип после создания в UI заблокирован. Есть quick-add из transaction/budget/withdraw forms и управление в Settings.

`goal_expense` — ещё одна системная pseudo-category. Она не входит в обычный список expense categories и присваивается только целевому расходу.

Пробелы:

- ссылка по key, а не FK;
- удаление категории оставляет dangling text references;
- одинаковый custom key разрешён для income и expense, а некоторые label lookups не передают type и могут выбрать не ту категорию;
- нет архивации категории, которая уже использована.

## 8. Цели и «отложено»

### Накопление

Для linked goal накоплено:

```text
saved_now = current balance of linked savings account
spent_on_goal = Σ expense.amount where goal_id = goal.id
funded_lifetime = saved_now + spent_on_goal
progress = min(100%, funded_lifetime / target_amount)
```

Статус: `работает` по текущей формуле. Такая формула сохраняет lifetime progress после целевых расходов. Любые нецелевые расходы/переводы со linked account снижают funded amount, потому что они не входят в `spent_on_goal`.

Для goal без account берётся `saved_amount`; новому goal UI всегда записывает 0, а изменить его нельзя. Эта ветка фактически legacy/import-only — `не используется` для новых накоплений.

### Пополнение goal account

Кнопка «пополнить» открывает обычный transfer в этот account. Нового income на savings account не создаётся. Статус: `работает`.

### «Потратить на цель»

Создаёт expense с linked savings account, `category=goal_expense`, `goal_id=goal.id`. Затем отдельным запросом может обновить status/completed_at. Статус: `частично`:

- если expense insert успел, а goal update упал, деньги уже списаны;
- checkbox «отметить выполненной» может поставить `spent` при любой сумме;
- при сумме cumulative goal expenses >= target goal получает `spent`;
- отмена/возврат целевой покупки не моделируется.

### Статусы goals

- `active`: `работает`;
- `reached`: `частично` — UI вычисляет его, но пополнение account не обновляет stored `goals.status`;
- `spent`: `работает`, но не откатывается при удалении expense;
- `cancelled`: `не используется` в UI, доступен через SQL/import/API.

## 9. Мультивалютность

Общий статус: `частично`.

Что работает:

- `profiles.currency` играет роль base/display currency;
- account имеет неизменную по смыслу currency, transaction снимает currency snapshot;
- обычная transaction должна совпадать с account currency;
- цель можно связать только с savings account той же currency;
- счета и goals отображаются в собственной currency;
- total accounts группируется по currencies без ложного сложения MDL+EUR+USD;
- историческая exchange-операция хранит обе суммы и курс.

Чего нет:

- нет таблицы/сервиса курсов;
- нет base-currency equivalent на transaction/account;
- нет общего капитала в base currency;
- analytics просто отбрасывает операции не в `state.currency`, а не конвертирует их;
- смена profile currency не меняет валюту уже созданных accounts/transactions/goals;
- БД не запрещает после создания изменить account currency и тем самым рассогласовать её с историей.

## 10. Авторизация, Supabase и RLS

### Auth

Статус: `частично` из-за незавершённого password recovery.

- Supabase JS v2 подключён через CDN.
- В frontend лежат project URL и anon key; для Supabase это ожидаемо. Service-role key в проекте не обнаружен.
- `persistSession=true`, `autoRefreshToken=true`.
- На sign-out in-memory workspace очищается.
- Профиль создаётся/обновляется клиентом после session; trigger на insert profile создаёт основной regular account.

### RLS

Статус: `работает` для изоляции данных по `user_id`, с оговорками ниже.

- RLS включён на profiles, accounts, categories, transactions, budgets, goals, liabilities.
- Для всех финансовых таблиц `FOR ALL USING (auth.uid() = user_id) WITH CHECK (...)`.
- Profile: user читает/вставляет/обновляет себя; admin может читать/обновлять все profiles.
- Trigger `protect_profile_admin_fields` не даёт не-admin изменить свои `billing`/`is_admin`.
- Admin не получает special access к accounts/transactions/goals других users.
- Policy на delete profile нет, поэтому admin-delete не имеет ни UI-, ни RLS-бэкенда.
- Policies не указывают `TO authenticated`, но для anon `auth.uid()` равен NULL, поэтому строки всё равно не доступны.

### Миграции и setup

Статус: `частично`. `supabase/schema.sql` содержит текущую полную схему для новой базы. Но `SUPABASE_SETUP.md` для existing project упоминает только migration `2026-06-19_accounts_multicurrency_goals.sql`. Он не перечисляет необходимые для текущего frontend более поздние migrations admin/billing, liabilities и user categories. База, обновлённая строго по этой инструкции, может не иметь таблиц/полей, к которым уже обращается UI.

### Server-side validation

Статус: `частично`.

Работает:

- amount > 0, supported currency, shapes типов;
- ownership всех linked accounts/goals;
- account/transaction currency matching;
- goal только с тем же user, savings account и той же currency;
- goal-linked transaction только expense с нужного account;
- available balance на expense/transfer/exchange insert/update;
- same-currency transfer требует равные суммы.

Не закрыто:

- balance check — read-then-write без row/advisory lock; два параллельных expense могут оба увидеть старый баланс и создать overdraft;
- delete/update прихода и delete transfer могут сделать другой account отрицательным;
- parent account update не перевалидирует дочерние transactions/goals;
- liability settlement validation не доказывает semantic match amount/type/account;
- нет atomic RPC для goal spending, liability settlement и destructive import.

### Отдельный security-риск UI

Многие user-controlled значения (`description`, account/category/goal names, comments, avatar URL) интерполируются в `innerHTML` без HTML escaping. RLS изолирует users на уровне строк, но не устраняет stored/DOM XSS в сессии самого user. Статус защиты вывода: `частично`.

## 11. Где что хранится и вычисляется

### Supabase/PostgreSQL

- `auth.users` и Supabase auth identities/session backend;
- `profiles`;
- `accounts`, включая opening balance и include flag;
- custom `categories`;
- all `transactions`, включая FX amounts/rate;
- `budgets`;
- `goals`, включая legacy saved amount и stored status;
- `liabilities` и settlement references.

### `localStorage`

- `norocel-language`;
- `norocel-theme`;
- Supabase SDK также хранит свою сессию в browser storage, так как `persistSession=true` (ключ задаётся SDK, а не кодом Norocel).

`currency` после входа берётся из profile и отдельным Norocel localStorage key не хранится.

### `sessionStorage`

Код Norocel `sessionStorage` не использует.

### Только в памяти client

- текущий page, month/year, transaction filter, open menu;
- hydrated copies всех entities;
- active account for history;
- admin stats;
- Chart.js instances;
- текущие modal/confirm states.

### Вычисляется на client

- account balance;
- dashboard/month analytics, savings rate, average;
- budget spending/progress;
- balances grouped by currency;
- goal saved/spent/funded/progress/computed status;
- open liability totals by currency;
- all chart datasets and top categories;
- admin retention counters.

SQL дублирует только account balance для валидации списаний.

## 12. Импорт и экспорт

### JSON export

Статус: `работает`.

Файл `norocel-backup-YYYY-MM-DD.json` содержит весь workspace. Profile выгружается в DB snake_case, остальные entities — в client camelCase. Формат не versioned и не имеет schema marker.

### JSON import

Статус: `частично`.

Алгоритм:

1. Параллельно удаляет current transactions, liabilities, goals, accounts, categories, budgets.
2. Вставляет categories, accounts, goals, transactions, liabilities, budgets.
3. Частично обновляет profile.

Риски:

- нет Postgres transaction/RPC; backup не восстанавливается при ошибке;
- результаты delete-запросов не проверяются через `throwIfError`;
- параллельное удаление связанных таблиц может гоняться с FK actions/constraints;
- файл не валидируется до удаления;
- avatar/email/billing/admin fields не восстанавливаются; это безопасно для privileged fields, но формат не является полным round-trip;
- UI-подсказка говорит только о замене transactions/budgets/goals, хотя также заменяются accounts/categories/liabilities.

### CSV

Статус: `не используется`. Ни CSV import, ни CSV export, ни mapping bank columns в коде нет.

## 13. Функции в коде, но не в доступном UI

| Функция | Статус | Комментарий |
|---|---|---|
| Update transfer/exchange через общий `saveTransaction` | `не используется` | API может, UI edit открывается только для income/expense. |
| Delete transfer/exchange | `не используется` | Handler/markup есть, но ни один доступный renderer не показывает actions для transfer. |
| Goal `cancelled` | `не используется` | DB/store/render знают status, команды отмены/возобновления нет. |
| Legacy `goals.saved_amount` | `не используется` для new UI data | Читается для goal без account, но нет UI пополнения. |
| Admin count `admins` | `не используется` | Вычисляется `getAdminStats`, но не рисуется на admin dashboard. |
| `GOAL_STATUSES` config | `не используется` | Экспортируется, но не импортируется. |
| Regular account history/actions | `частично` | Расчёты и action handlers работают с любым account, но regular accounts не рисуются карточками. |

## 14. Видимые, но частичные или заглушечные функции

- `частично` Password reset: нет установки нового пароля.
- `частично` «Баланс» dashboard/report: это net flow, а не account/net-worth balance.
- `частично` Transactions: transfers/exchanges скрыты из общего журнала.
- `частично` Budget action: открывает нужную форму, но подпись говорит о добавлении категории, а не бюджета.
- `частично` Accounts: UI показывает savings, но не regular.
- `частично` Ручной FX rate: поле есть, но суммы не рассчитываются и курс не проверяется/не показывается после сохранения.
- `частично` Goal completion: два запроса, stale statuses при delete.
- `частично` Liability settlement: два запроса, loose semantic link.
- `частично` JSON import: destructive, non-atomic, insufficient validation.
- `частично` Email change: Auth/profile update не атомарны, confirmation state не показан.
- `заглушка` Admin user delete: пустая click-handler branch.

## 15. Инварианты финансовой модели

Ниже разделены реально закреплённые правила и правила, которые только предполагаются UI.

### Закреплено в БД

1. Каждая финансовая строка имеет user owner; RLS даёт user работать только со своими строками.
2. `amount > 0`; opening balance неотрицателен; goal target и budget limit положительны.
3. Income/expense имеют ровно один account; transfer/exchange имеют from/to и обе суммы.
4. Валюта income/expense равна account currency; валюты двух сторон transfer равны валютам двух accounts на момент insert/update.
5. Источник и назначение transfer не могут совпадать.
6. Одинаковая валюта transfer требует одинаковые debit/credit amounts.
7. Expense/transfer/exchange не может превысить balance при своём insert/update.
8. Goal account принадлежит user, имеет `type=savings` и ту же currency; один account связан не более чем с одной goal.
9. Goal-linked transaction — только expense в goal currency и, если account привязан, именно с него.
10. Transfer/exchange атомарен как одна row mutation.

### Действия, меняющие баланс

| Действие | Изменение баланса | Связанные записи |
|---|---|---|
| Создать account | `+ opening_balance` в формуле account | Только account; transaction не создаётся. |
| Создать income | `+ amount` на account | 1 transaction. |
| Создать expense | `- amount` на account | 1 transaction. |
| Transfer в одной currency | `- amount` source, `+ amount` target | 1 transfer transaction. |
| Exchange | `- amount` source, `+ converted_amount` target | 1 exchange transaction с currency pair/rate. |
| Списать на goal | `- amount` с savings account | 1 goal-linked expense, затем optional goal update отдельным запросом. |
| Погасить receivable | `+ liability.amount` | 1 income, затем liability update отдельным запросом. |
| Погасить payable/credit | `- liability.amount` | 1 expense, затем liability update отдельным запросом. |
| Редактировать transaction | Старый эффект исчезает, новый применяется в расчёте | Та же row; нет reversal/audit row. |
| Удалить transaction | Её эффект полностью исчезает | Row удаляется; linked metadata может остаться stale. |

### Действия, не меняющие баланс напрямую

Создание/редактирование goal, category, budget и open liability не меняет account balance. Смена base currency меняет выборку/формат analytics, но не деньги.

### Предполагаются, но не гарантированы во всех переходах

- account balance никогда не отрицателен;
- account currency/type не меняются вопреки истории и goal links;
- stored goal status совпадает с computed status;
- settled liability всегда имеет согласованную settlement transaction;
- manual exchange rate равен фактическому отношению сумм;
- destructive import либо полон, либо полностью откачен.

## 16. Предлагаемая модель сущностей для переноса на React

Перенос на React не должен менять финансовую семантику случайно. Рекомендуется сначала зафиксировать доменные инварианты и Supabase RPC, затем менять view layer.

### Целевые сущности

#### `UserProfile`

```text
id, email, displayName, avatarUrl,
locale, baseCurrency,
billingPlan, isAdmin,
createdAt, updatedAt, lastSeenAt
```

Auth identity остаётся в Supabase Auth; privileged fields меняются только admin-командой.

#### `Account`

```text
id, userId, name,
kind: regular | savings,
currencyCode,
includeInNetWorth,
status: active | archived,
createdAt, updatedAt
```

Начальный остаток лучше оформить immutable opening operation/posting, а не редактируемым полем. Currency account после первой проводки должна быть immutable; вместо delete — archive.

#### `FinancialOperation`

```text
id, userId,
kind: income | expense | transfer | exchange | adjustment,
occurredOn, description,
categoryId?, goalId?,
status: posted | voided,
createdAt, updatedAt
```

Это user-visible header. Его нельзя хранить отдельно от postings без DB transaction.

#### `Posting`

```text
id, operationId, accountId,
direction: debit | credit,
amountMinor, currencyCode,
role: source | destination | primary,
createdAt
```

Рекомендуется хранить деньги как integer minor units или использовать строгую decimal-библиотеку; не считать деньги JS `Number` в domain layer. Income/expense имеет одну account posting в упрощённой personal-finance модели; transfer/exchange — ровно две.

#### `ExchangeDetails`

```text
operationId,
sourceAmountMinor, sourceCurrency,
targetAmountMinor, targetCurrency,
rate, rateConvention,
rateSource: manual | provider,
quotedAt?
```

Может быть полями `FinancialOperation`, если не нужна отдельная таблица. Важно явно зафиксировать rate convention и сверять rate с двумя amounts.

#### `Category`

```text
id, userId?, stableCode,
kind: income | expense,
displayName?, isSystem,
status: active | archived,
createdAt, updatedAt
```

И system, и custom categories должны иметь стабильную identity. `FinancialOperation.categoryId` — FK. Используемую category надо архивировать, а не удалять.

#### `Goal`

```text
id, userId, name,
targetAmountMinor, currencyCode,
savingsAccountId?,
deadline?, icon?,
status: active | reached | spent | cancelled,
reachedAt?, completedAt?,
createdAt, updatedAt
```

Баланс в goal не хранить. Goal progress читается domain selector-ом или DB view. Status transitions выполнять командами/RPC, а не произвольным CRUD.

Если в будущем нужно распределение одного account между целями, добавить `GoalAllocation(goalId, accountId, allocatedAmount)`, а не перегружать account balance.

#### `Budget`

```text
id, userId, categoryId,
periodMonth,
limitAmountMinor, currencyCode,
createdAt, updatedAt
```

Период и currency должны быть явными. Для repeating budget можно отделить `BudgetTemplate` от месячного snapshot.

#### `Liability`

```text
id, userId, counterpartyName,
kind: receivable | payable | credit,
principalAmountMinor, currencyCode,
dueOn?, comment?,
status: open | settled | cancelled,
createdAt, updatedAt
```

#### `LiabilitySettlement`

```text
id, liabilityId, operationId,
amountMinor, currencyCode,
settledAt
```

Отдельная settlement entity даёт partial payments и не позволяет разорвать связь простым delete transaction. Создание operation+settlement должно быть одним RPC.

### Агрегаты/проекции, а не таблицы-источники

- `AccountBalance` — SQL view/RPC + React Query selector;
- `NetWorthByCurrency`;
- `MonthlyCashflow`;
- `BudgetProgress`;
- `GoalProgress`;
- `OpenLiabilityTotals`.

Эти значения не нужно дублировать в React state как независимые mutable fields.

### Команды/RPC, которые нужны до React-переноса или вместе с ним

1. `post_operation(...)` — атомарно создаёт operation/postings, лочит affected accounts и проверяет balance.
2. `amend_operation(...)` / `void_operation(...)` — повторно проверяет все affected balances и linked domain state; для audit лучше void/reversal, а не hard delete.
3. `spend_for_goal(...)` — в одной транзакции создаёт expense и меняет goal status.
4. `settle_liability(...)` — в одной транзакции создаёт financial operation и settlement.
5. `import_workspace(...)` — валидирует versioned document, затем атомарно заменяет workspace.

### Предлагаемые React-модули

```text
app/                 routing, providers, auth guard
features/auth/       sign-in, sign-up, recovery
features/dashboard/  cash-flow projections
features/accounts/   accounts, balances, history
features/operations/ journal, forms, transfers, exchange
features/categories/ system/custom category management
features/budgets/    monthly budgets
features/goals/      goals, progress, spend flow
features/liabilities/liabilities and settlement
features/reports/    reporting projections
features/settings/   profile, locale, theme, backup
features/admin/      profile administration
domain/money/        Money, Currency, Rate, rounding
domain/finance/      invariants and command payloads
data/supabase/       generated DB types, queries, RPC clients
```

Серверные entities следует загружать через query cache (например, TanStack Query), а UI-only state — держать локально/в маленьком UI store. Не следует копировать весь Supabase workspace в один глобальный mutable React context.

## 17. Приоритеты перед переносом

1. Закрыть атомарность goal spend, liability settlement и import.
2. Закрепить balance invariant при concurrent writes, edit и delete, а не только при insert outgoing transaction.
3. Запретить менять account currency/type после появления истории/связи или делать атомарную migration operation.
4. Сделать единый operation journal с income, expense, transfer и exchange, с редактированием/отменой по правилам.
5. Показывать regular и savings accounts в одной модели accounts, а не прятать regular из account management UI.
6. Заменить category text links на FK/archive semantics.
7. Добавить currency+period к budgets и определить одну семантику «баланса» для UI.
8. Завершить password recovery и валидировать/экранировать user strings при render.
9. До любого React rewrite зафиксировать текущую модель contract/интеграционными тестами: в репозитории сейчас нет автотестов.

---

Эта ревизия описывает текущую реализацию. Код, SQL-схема и runtime data в рамках ревизии не изменялись.
