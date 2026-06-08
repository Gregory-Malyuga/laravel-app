# Roadmap

## В работе

_Нет активных задач._

## Сделано

### CQRS: команды не возвращают сущности ✓

Завершено 2026-06-08.

- `CreateUserHandler`, `RegisterHandler` → `return int $id`
- `UpdateUserHandler`, `DeleteUserHandler`, `LogoutHandler` → `return null`
- Контроллеры делают `ask(FindUserByIdQuery($id))` после dispatch
- `MakeDomainCommand` стабы обновлены
- `RegisterHandlerTest` проверяет `assertIsInt`

### HandlerInterface → CommandHandlerInterface / QueryHandlerInterface ✓

Завершено 2026-06-08. Подробности: ADR-0004.

- `HandlerInterface` удалён
- `CommandHandlerInterface`: `handle(): ?int`
- `QueryHandlerInterface`: `handle(): object`
- Все 8 хэндлеров доменов обновлены
- Bus-тесты обновлены

### OpenAPI-генератор: input/output DTO ✓

Завершено 2026-06-08.

- `{Name}Resource` → response schema
- `Create{Name}Data` → POST requestBody
- `Update{Name}Data` → PUT requestBody
- `safeClassExists()` для защиты от Laravel ErrorException (HIST-004)

### Инфраструктура: ES, .env.example, composer setup ✓

Завершено 2026-06-08.

- `elasticsearch:setup` добавлен в `composer setup`
- `.env.example` отражает реальный Docker-стек
