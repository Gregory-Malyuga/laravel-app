# State

> Этот файл ведёт агент. Человек читает, но не редактирует вручную.

## Now

Незакоммиченные изменения в User домене и Shared. Качество-гейт проходит (Pint, PHPStan level 8, Deptrac, тесты зелёные).

## Recent decisions

- **`UserRepositoryInterface` в Application слое** — ports & adapters: интерфейс в `Application/Repositories/`, реализация в `Infrastructure/Repositories/`. Все 7 handlers переведены на интерфейс. Deptrac обновлён: `ApplicationLayer` больше не может импортировать Infrastructure напрямую.
- **Auth домен упразднён, влит в User** — `Auth` не имел собственной сущности; контроллеры, команды, handlers и DTOs перенесены в `Domains/User`. `AuthServiceProvider` слит с `UserServiceProvider`.
- **`LogoutHandler` через `PersonalAccessToken::whereKey()->delete()`** — прямой query без загрузки модели; обход PHP 8.5-бага с `void` vs `mixed` return type в `HandlerInterface`.
- **`unique:users,email` только в create-сценарии** — убран из `UserData::rules()` (общий DTO для create+update), добавлен в `UserController::store()` через `$request->validate()`. `Rule::unique()->ignore($id)` нужен для update — открытый пункт.
- **`UpdateUserHandler` захватывает return `update()`** — `BaseRepository::update()` возвращает `$model->fresh()`; теперь событие и ответ содержат актуальные данные.
- **`SortData` — вайтлист колонок в домене** — `SortData::fromRequest()` не принимает `allowedSortColumns`; валидация `sort` поля через `array_merge(SortData::rules(), ['sort' => Rule::in(SORTABLE)])` в `ListUsersQuery::fromRequest()`.

## Last updated

2026-06-08

## Last commit

fix: run vendor binaries via php to survive artifact permission loss
