# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Незакоммиченные изменения в User домене и Shared. Ожидают коммита тремя группами (см. ниже).  
PHPStan level 8 — проходит после фикса `@extends ModelNotFoundException<User>`.

## Recent decisions

- **`UserRepositoryInterface` в Application слое** — ports & adapters: интерфейс в `Application/Repositories/`, реализация в `Infrastructure/Repositories/`. Все 7 handlers переведены на интерфейс. Deptrac обновлён: `ApplicationLayer` больше не может импортировать Infrastructure напрямую.
- **Auth домен упразднён, влит в User** — `Auth` не имел собственной сущности; контроллеры, команды, handlers и DTOs перенесены в `Domains/User`. `AuthServiceProvider` слит с `UserServiceProvider`.
- **`LogoutHandler` через `PersonalAccessToken::whereKey()->delete()`** — прямой query без загрузки модели; обход PHP 8.5-бага с `void` vs `mixed` return type в `HandlerInterface`.
- **`unique:users,email` только в store/update** — убран из `UserData::rules()` (общий DTO для create+update); `Rule::unique('users')` в `store()`, `Rule::unique('users')->ignore($id)` в `update()`.
- **`UpdateUserHandler` захватывает return `update()`** — `BaseRepository::update()` возвращает `$model->fresh()`; теперь событие и ответ содержат актуальные данные.
- **`ListEntityQuery` базовый класс** — константа `SORTABLE` в каждом домене, `fromRequest()` объединяет валидацию sort + pagination + filters; `UserFilterData::rules()` для авто-валидации через Spatie.
- **`UserDeleted` хранит `int $id`, не модель** — убран `SerializesModels`; `id` сохраняется до `delete()`, передаётся в событие; предотвращает падение десериализации в очереди после удаления записи.
- **`UserElasticsearchSyncListener` реализует `ShouldQueue`** — ES-синхронизация асинхронная, очередь `imports`; `handleDeleted` принимает `$event->id`.
- **`BaseFilterBuilder` fallback — намеренный дизайн** — поле в `FilterData`, не указанное в `filterMap`, уходит в `WHERE Str::snake($key) = $value` автоматически. Позволяет `make:domain` давать рабочую фильтрацию без ручных правок. Подробности: ADR-0003.
- **`UserNotFoundException` → HTTP 404** — зарегистрирован в `bootstrap/app.php` через `$exceptions->render()`; `@extends ModelNotFoundException<User>` для PHPStan.

## Last updated

2026-06-08

## Last commit

fix: run vendor binaries via php to survive artifact permission loss
