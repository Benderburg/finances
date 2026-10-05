# noros/core

Независимый Composer-пакет инфраструктуры Noros на PHP 8.3+ / Laravel 13 / Filament 5.
Не импортирует CMS или магазин. Composer auto-discovery регистрирует provider;
в PanelProvider подключите `new Noros\Core\Filament\CorePlugin()`.

Содержит настройки, locale middleware, библиотеку переводов, SEO, медиа, пользователей,
роли и разрешения. `CoreSeeder` создаёт роли и индекс системных переводов, но не пароль.
`SiteSeo` хранит общие и локализованные значения в настройке `site.seo`.
Страница `/admin/site-seo-settings` (право `manage_settings`) управляет метаданными,
организацией, территорией, контактами, sitemap/robots и политикой перевода.
`Seo` и Blade-компонент формируют escaped canonical, robots, hreflang,
Open Graph, Twitter и JSON-LD. Данные бренда и региона задаёт приложение.
`php artisan noros:admin` создаёт администратора с интерактивным паролем.

`config/noros.php` задаёт каталог локалей и permissions. Настройка `platform` в Filament
управляет enabled/default/fallback/timezone; изменение атомарно вызывает
`PlatformConfigurationChanged`, на который модули подписываются для обновления индексов.
`Settings` хранит данные клиента; `TranslationLibrary` реализует DB → файлы → fallback,
импорт с preview и экспорт JSON. Пакетные языковые файлы имеют namespace `noros-core`.

Локальное подключение: Composer path repository `packages/noros-core`, symlink=true,
require `noros/core:dev-main`. Новый клиент создаётся из noros-starter. Для выпуска
используйте собственный VCS/Composer registry и согласованные version tags; dev-main
служит текущей локальной разработке, не фиксированной production-версией.

Тестовый Laravel-host находится в noros.net: `php artisan test` и
`php vendor/bin/phpstan analyse`. CI пакета проверяет его в этом host с текущим исходным
кодом пакета. По умолчанию CI берёт `NorosStudio/lara-noros` из `master`,
а пакеты `NorosStudio/noros-core`, `noros-cms` и `noros-shop` — из `main`.
GitHub variables `NOROS_*_REPOSITORY` и `NOROS_*_REF` позволяют переопределить эти
источники. Для закрытых репозиториев нужен secret `NOROS_PACKAGES_READ_TOKEN` с правом
чтения. Код клиента не является runtime-зависимостью пакета.

Настройки платформы загружаются на каждый web-запрос через middleware HTTP Kernel.
Регистрация сохраняется при последующем обновлении групп middleware другими пакетами.

### Cache / Redis

`CacheService::remember($group, $key, $loader, $ttl)` кеширует восстанавливаемые данные;
`invalidate(...$groups)` меняет UUID поколения группы в `noros_cache_versions`.
Поколение находится в основной БД и меняется в транзакции записи модели: откат
не публикует незафиксированные данные, восстановление Redis не возвращает старый кеш.
Внутри транзакций чтение и заполнение кеша отключены. До миграции таблицы кеш обходится.
Bulk SQL updates не вызывают model events: после них вызывайте `invalidate()` явно.
Корзины, заказы, сессии и rate limits не входят в обычную очистку кеша сайта.

`NOROS_REDIS_ENABLED=true` или `CACHE_STORE=redis` включает framework failover
Redis → `NOROS_CACHE_FALLBACK=file` (также поддерживается `database`). Для нескольких
серверов выберите общий database fallback; файловый fallback локален одному серверу.
Redis требует доступного PHP Redis-клиента; отсутствие расширения безопасно включает
fallback. Драйвер сессий и очередей автоматически не меняется. Rate limiter всегда
использует fallback, поэтому outage/reconnect Redis не обнуляет его счётчики.
Ошибка соединения открывает circuit на текущий request/job, последующий scope
пробует Redis вновь. В лог попадает только имя драйвера, без URL и credentials.
Default timeout/read_timeout соединения кеша ограничены одной секундой.

TTL задаётся через `NOROS_CACHE_TTL`, либо по группам:
`NOROS_CACHE_SETTINGS_TTL`, `NOROS_CACHE_CMS_TTL`, `NOROS_CACHE_SHOP_TTL`,
`NOROS_CACHE_ENGAGEMENT_TTL`; явный TTL вызова имеет приоритет.
`Settings` загружает одну карту настроек, выбирает locale при чтении и сбрасывает
карту вместе с группами CMS/Shop/Engagement после model mutations.
`/admin/cache-status` доступен по `manage_settings`: состояние, безопасный host/port,
ping, повторная проверка и подтверждаемая очистка только данных сайта.

### Security defaults

Глобальные заголовки ограничивают frame/object/base, запрещают MIME sniffing,
снижают передачу referrer и отключают неиспользуемые browser permissions.
HSTS добавляется только при production HTTPS; настройте trusted proxies в host
до включения TLS termination. Inline scripts и Livewire сохраняют совместимость.
Административные, авторизованные, checkout/cart ответы имеют `private, no-store`.
Filament image uploads принимают только JPEG/PNG/WebP/GIF, до 10 MiB;
имена файлов генерируются случайно, расширение определяется серверным MIME.
MediaResource сохраняет существующую поддержку PDF/TXT.
