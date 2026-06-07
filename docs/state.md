# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Idle.

## Recent decisions

- **`MakeDomainCommand::formatGenerated()` пропускает Pint в `testing`** — каждый тест запускал `exec(pint ...)` через PHP-процесс, давая ~3-8s потерь на тест. Guard `app()->environment('testing')` устраняет лишний fork без влияния на реальную генерацию.
- **`LaravelCommandBus/QueryBusTest` → `PHPUnit\Framework\TestCase`** — тесты не используют `$this->app`, но платили ~2.9s за cold boot Laravel. Прямое наследование от PHPUnit устраняет boot полностью.
- **`MakeDomainCommandTest::setUp()` чистит артефакты** — добавлен `cleanupStubGenArtifacts()` в setUp, чтобы тест был идемпотентен при повторных запусках и не зависел от tearDown предыдущей сессии.

## Last updated

2026-06-07

## Last commit

fix: diagnose and reduce test suite slowness
