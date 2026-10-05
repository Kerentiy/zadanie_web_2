# php-eloquent-skeleton

Минимальный скелет PHP-приложения без фреймворка:

| Библиотека | Зачем |
|---|---|
| [`illuminate/database`](https://laravel.com/docs/12.x/eloquent) 12.x | Eloquent ORM + Query Builder + Schema Builder (standalone, через `Capsule`) |
| `illuminate/events`, `illuminate/filesystem` | Нужны для мигратора и логирования SQL-запросов |
| [`monolog/monolog`](https://seldaek.github.io/monolog/doc/01-usage.html) 3.x | Логирование |
| `vlucas/phpdotenv` | Конфигурация через `.env` |

Требуется PHP ≥ 8.2 с расширениями `pdo_sqlite` (или `pdo_mysql`) и `mbstring`.

## Быстрый старт

```bash
composer install
cp .env.example .env
php bin/migrate migrate      # создаст database/database.sqlite и таблицу users
composer serve               # http://localhost:8000
```

## Структура

```
bin/migrate                 CLI мигратора
config/                     app.php, database.php, logging.php (значения из .env)
database/migrations/        миграции (формат как в Laravel)
public/index.php            точка входа: Eloquent + логирование запросов
src/Application.php         bootstrap: .env -> config -> Monolog -> Eloquent
src/Database/Database.php   подключение Eloquent (Capsule), логирование SQL
src/Database/MigrationRunner.php   обёртка над Illuminate Migrator
src/Http/HttpLogger.php     лог каждого HTTP-запроса и тела ответа
src/Logging/LoggerFactory.php      настройка Monolog (handler, formatter, processors)
src/Models/User.php         пример Eloquent-модели
storage/logs/               логи (app-YYYY-MM-DD.log)
```

## Eloquent

Подключение настраивается в `.env` (`DB_CONNECTION=sqlite|mysql`, `DB_*`) и поднимается в
`App\Database\Database::boot()`. После этого модели работают статически, как в Laravel:

```php
$user = App\Models\User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
App\Models\User::where('email', 'ada@example.com')->first();
```

## Миграции

```bash
php bin/migrate migrate                       # выполнить новые миграции
php bin/migrate status                        # статус
php bin/migrate rollback [N]                  # откат последнего batch (или N миграций)
php bin/migrate fresh                         # откатить всё и накатить заново
php bin/migrate make add_phone_to_users --table=users
php bin/migrate make create_posts_table --create=posts
```

Тестовая миграция: `database/migrations/2026_10_05_000000_create_users_table.php`.

## Логирование (Monolog)

Настроено по [руководству Monolog](https://seldaek.github.io/monolog/doc/01-usage.html):

* **Logger + handler stack.** Один `Logger` с `RotatingFileHandler` (`Level` из `LOG_LEVEL`, ежедневная ротация,
  хранится `LOG_MAX_FILES` файлов) → `storage/logs/app-YYYY-MM-DD.log`.
* **Formatter.** `LineFormatter` привязан к handler: `[время] канал.УРОВЕНЬ [request-id]: сообщение {контекст}`.
* **Processors** (`pushProcessor`): `PsrLogMessageProcessor` (плейсхолдеры `{uri}`) и `UidProcessor` (`extra.uid`).
* **Каналы** (`Logger::withName()`): `app` и `http` делят один handler и пишут в один файл;
  фильтровать удобно по каналу: `grep ' http\.' storage/logs/app-*.log`.
* **Уровни** выбираются по смыслу из раздела Log Levels: SQL-запросы — INFO (`LOG_QUERIES=true`),
  HTTP 2xx/3xx — INFO, 4xx — WARNING, 5xx — ERROR, необработанное исключение — CRITICAL.
* Каждый ответ содержит заголовок `X-Request-Id`; этот же id есть во всех записях запроса.

### Лог HTTP-запросов (канал `http`)

`src/Http/HttpLogger.php` подключается одной строкой в начале `public/index.php`. Он буферизует вывод
и пишет запись в `shutdown`-функции, поэтому лог создаётся даже при `exit()` или фатальной ошибке.
В запись попадают: метод, URI, IP, заголовки и тело запроса, статус, заголовки и **тело ответа**,
длительность. Уровень записи зависит от статуса: `2xx/3xx` — INFO, `4xx` — WARNING, `5xx` — ERROR.

* Заголовки `Authorization`, `Cookie` и т. п. и JSON-поля `password`, `token`… заменяются на `***`
  (списки в `config/logging.php`).
* Тела длиннее `LOG_BODY_MAX_BYTES` (по умолчанию 10 КБ) обрезаются, бинарные данные не пишутся.

Пример:

```
[2026-10-05 18:35:56.206] http.INFO [50035363c204dc58]: POST /users -> 201 {"request":{"ip":"127.0.0.1","headers":{...,"authorization":"***"},"body":"{\"name\":\"Иван\",\"email\":\"ivan@example.com\",\"password\":\"***\"}"},"response":{"status":201,"headers":{...},"body":"{\"data\":{...}}"},"duration_ms":45.87}
```

## API примера

```bash
curl localhost:8000/
curl -XPOST localhost:8000/users -H 'Content-Type: application/json' -d '{"name":"Ada","email":"ada@example.com"}'
curl localhost:8000/users
curl localhost:8000/users/1
```

## Лицензия

MIT
