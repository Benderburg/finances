# noros/cms

Переиспользуемая CMS Laravel 13 / Filament 5, зависит только от noros/core.
Подключите `new Noros\Cms\Filament\CmsPlugin()` в PanelProvider.
Provider автоматически загружает migrations, routes, views и переводы.

Страницы и шаблоны, расширяемый BlockRegistry, меню, блог, портфолио, комментарии,
модерация и оценки 1–5. Features pages/blog/portfolio/comments/ratings/menus отключаются
в config/noros-cms.php до построения routes (после изменения очистите route/config cache).
Шаблоны переопределяются стандартно в resources/views/vendor/noros-cms;
config views позволяет использовать собственные Blade views приложения.

Переводы scalar-полей и блоков хранятся в cms_content_translations; индекс locale/path
и история cms_slug_redirects поддерживаются транзакционно LocalizedContent.
Редактор видит настоящую заполненность перевода, публичный сайт использует fallback.
Структурные изменения блоков сначала делаются в исходном контенте, затем переводятся.
`ContentSeo` объединяет SEO модели с настройками core; canonical, robots,
социальные поля и вложенный JSON-LD редактируются и переводятся в Filament.
По умолчанию fallback индексируется как прежде; `site.seo.require_translation`
исключает отсутствующие переводы из hreflang/sitemap и даёт им noindex с canonical
исходной версии. Sitemap содержит только опубликованные канонические URL.
`/robots.txt` использует настройки core; статический файл приложения нужно удалить.
Блог: фильтры noindex, пагинация с собственным canonical, просмотры не меняют lastmod.
`BlockRegistry::localizedData()` объединяет inline `data.translations[locale]`
с исходными данными для клиентских и универсальных шаблонов.

`BlockRegistry::register(type, view, schema)` добавляет блок;
`registerTemplate(key, label, view)` — клиентский шаблон. StandardBlocks предоставляет
универсальные блоки без брендинга Noros. Клиентский provider может переопределять view/schema.
Модуль classifieds может зависеть от core + cms и регистрировать собственные модели,
плагин, события и engagement alias, не добавляя объявления в CMS.

Контактная форма включается CMS_CONTACT_ENABLED=true и CMS_CONTACT_EMAIL;
для уведомлений модераторам задайте CMS_MODERATION_EMAIL и настройте mail/queue.
По умолчанию внешние письма выключены.

React + TypeScript rating widget: `npm ci && npm run build` в этом пакете.
Собранный dist входит в репозиторий. В приложении выполняйте
`php artisan vendor:publish --tag=noros-cms-assets --force` после Composer install/update.

Для существующей БД: backup → `php artisan migrate` →
`php artisan noros:content:backfill --dry-run` → `php artisan noros:content:backfill`.
Backfill не меняет тексты, авторов, исходные даты или старые likes.
Новые клиентские данные принадлежат сидерам приложения.

CI проверяет текущую ревизию пакета в `NorosStudio/lara-noros` из `master`,
с core и shop из `main`. Источники можно изменить через `NOROS_*_REPOSITORY`
и `NOROS_*_REF`; host должен содержать тесты для актуального контракта CMS.

Локальное подключение через Composer path symlink, require noros/cms:dev-main.
Тестовый host — noros.net: `php artisan test`, `php vendor/bin/phpstan analyse`.
CI variables и процедура публикации описаны в README noros/core и документации workspace.
