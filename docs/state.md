# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Незакоммичены изменения в `app/Domains/User/Domain/Models/User.php` и `UserOpenApi.php` — природа правок неизвестна, требуют ревью перед коммитом.

## Recent decisions

- **`MakeDomainCommand::formatGenerated()` пропускает Pint в `testing`** — каждый тест запускал `exec(pint ...)` через PHP-процесс, давая ~3-8s потерь на тест. Guard `app()->environment('testing')` устраняет лишний fork без влияния на реальную генерацию.
- **`LaravelCommandBus/QueryBusTest` → `PHPUnit\Framework\TestCase`** — тесты не используют `$this->app`, но платили ~2.9s за cold boot Laravel. Прямое наследование от PHPUnit устраняет boot полностью.
- **`MakeDomainCommandTest::setUp()` чистит артефакты** — добавлен `cleanupStubGenArtifacts()` в setUp, чтобы тест был идемпотентен при повторных запусках и не зависел от tearDown предыдущей сессии.
- **GitHub Actions CI** — добавлен `.github/workflows/ci.yml`: lint (×4) → analyze (phpstan + deptrac) → test (postgres + redis + elasticsearch) → build (prod image → ghcr.io) → trivy (fs + image) → smoke → release. Vendor передаётся через артефакт; образ тегируется SHA + ref_name, lowercase через `tr`.
- **`routes/api.php` очищен** — удалены маршруты `StubGen`-домена, который никогда не был создан; баг поймал smoke-тест.

## Last updated

2026-06-08

## Last commit

fix: run vendor binaries via php to survive artifact permission loss
