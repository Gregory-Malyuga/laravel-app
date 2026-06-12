# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Оптимизация тест-сьюта: время с 174s → целевые ≤50s.

**Незакоммиченные изменения:**
- `app/Shared/Console/Commands/MakeDomainCommand.php` — два guard-а `PHPUNIT_RUNNING`: пропуск `migrate` и `formatGenerated` (exec Pint) в тестах
- `phpunit.xml` — добавлена `PHPUNIT_RUNNING=1` env var
- `tests/Unit/Shared/Console/MakeDomainCommandTest.php` — bootstrap-оптимизация: 17 shared-тестов используют один make:domain call; `$migrationRan` флаг вместо `Schema::hasTable` в cleanup; `tearDownAfterClass` для финального restore
- `bootstrap/providers.php` — незначительные изменения (возможные side-effects от тестов)
- `routes/api.php` — аналогично
- `app/Domains/User/*`, `docker-compose.yml`, `.github/workflows/ci.yml` — из предыдущей сессии (UserStatus, mailpit)

**Удалён:** `tests/Unit/Shared/Console/BenchmarkTest.php` (временный профайлинг-файл).

**Текущий статус:** изменения не закоммичены. Docker не запущен — тесты не прогнаны.

`bootstrap/providers.php` и `routes/api.php` вручную очищены от мусора (StubGroup, BenchGen).

## Аудит 2026-06-08 — итог

Полный аудит проведён после CQRS-рефактора. Проект в хорошем состоянии.

### Закрыто / подтверждено ✓

- **ES маппинг** — `database/elasticsearch/users.json` корректный (`search_as_you_type` + `_2gram`/`_3gram`). `elasticsearch:setup` добавлен в `composer setup`. ✓
- **ES truncation header** — `AddEsSearchTruncatedHeader` подключён в api-группе (`bootstrap/app.php:25`). `EsTruncationBag` — singleton. `InteractsWithElasticsearch::fetchIdsFromElasticsearch()` вызывает `markTruncated()` при переполнении. ✓
- **Docker local dev** — health checks на `php`/`caddy`, redis без пароля, порт 6379 открыт — намеренно. Бой будет на Docker Swarm / Kubernetes с отдельным compose. ✓
- **PHP `^8.5` + `:latest` образ** — принято осознанно. ✓
- **CI pgsql vs SQLite** — проверено вручную, паритет есть. ✓
- **`.env.example`** — обновлён, отражает реальный стек. ✓

### Остаётся открытым (см. roadmap)

- `LogoutHandler` — прямой `PersonalAccessToken` в Application-слое
- ~10 решений из этого файла без ADR

## Recent decisions

- **`CommandHandlerInterface` / `QueryHandlerInterface`** — `HandlerInterface` удалён. Commands возвращают `?int`, Queries возвращают `object`. Подробности: ADR-0004.
- **`safeClassExists()`** — обёртка вокруг `class_exists()` для защиты от Laravel ErrorException при отсутствии файла. Используется в `GenerateOpenApiDocsCommand`. Подробности: HIST-004.
- **OpenAPI-генератор различает input/output DTO** — `{Name}Resource` для response schema, `Create{Name}Data` для POST requestBody, `Update{Name}Data` для PUT requestBody.
- **`elasticsearch:setup` добавлен в `composer setup`** — ES-маппинг (`search_as_you_type` с `_2gram`/`_3gram`) создаётся автоматически при инициализации проекта.
- **`.env.example` обновлён** — отражает реальный Docker-стек: pgsql, redis, elasticsearch, APP_URL=http://localhost:8088.
- **`BaseFilterBuilder` синтаксис** — `new X()->method()` → `(new X())->method()` (PHP 8.4 / PHPStan).
- **`assert($id !== null)`** — сужение `?int` до `int` в контроллерах после `dispatch()`.
- **`UserRepositoryInterface` в Application слое** — ports & adapters: интерфейс в `Application/Repositories/`, реализация в `Infrastructure/Repositories/`. Deptrac: `ApplicationLayer` не импортирует Infrastructure.
- **Auth домен упразднён, влит в User** — `Auth` не имел собственной сущности; контроллеры, команды, handlers и DTOs перенесены в `Domains/User`.
- **`ListEntityQuery` базовый класс** — константа `SORTABLE` в каждом домене, `fromRequest()` объединяет валидацию sort + pagination + filters. Подробности: ADR-0003.
- **`UserDeleted` хранит `int $id`, не модель** — убран `SerializesModels`; предотвращает падение десериализации в очереди после удаления записи.
- **`UserNotFoundException` → HTTP 404** — зарегистрирован в `bootstrap/app.php` через `$exceptions->render()`.

## Last updated

2026-06-12 (полное ревью рефактора MakeDomainCommand: 15 находок → roadmap R-1..R-15)

## Last commit

fix: restore useAppPath to fix getNamespace() detection
