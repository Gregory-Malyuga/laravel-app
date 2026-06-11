# Roadmap

## В работе

### Рефакторинг MakeDomainCommand → Generator-классы

**Цель:** разбить монолитный `MakeDomainCommand` (1 500 строк) на отдельные Generator-классы — по одному на каждый генерируемый тип файла. Это устраняет три известных бага и делает генератор дебажабельным.

**Известные баги, которые закрывает рефакторинг:**

| # | Баг | Симптом |
|---|-----|---------|
| B-1 | `assert($id !== null)` без `;` в `stubController()` (строка 1018) | Сгенерированный контроллер не проходит `php -l` |
| B-2 | Двойной вызов `migrate` — в `generateMigration()` и в `handle()` | Миграция гоняется дважды при каждом запуске |
| B-3 | `$this->ns` — mutable state на уровне команды | Команда не переиспользуема, генераторы нельзя тестировать изолированно |

**Целевая структура:**

```
app/Shared/Console/
  Commands/
    MakeDomainCommand.php           ← тонкий оркестратор
  DomainGenerator/
    Context/
      DomainContext.php             ← readonly DTO: name, ns, table, plural, fields, options
    Contracts/
      GeneratorInterface.php        ← generate(DomainContext, Filesystem): void
    Support/
      FieldParser.php               ← parseFields / inferType / typeDefinition / fakerForField
      TestValueHelper.php           ← testValueFor()
    Generators/
      Domain/
        ModelGenerator.php
        FactoryGenerator.php
        EventGenerator.php          ← Created / Updated / Deleted (loop внутри)
        NotFoundExceptionGenerator.php
      Application/
        Data/
          CreateDataGenerator.php
          UpdateDataGenerator.php
          ResourceGenerator.php
          FilterDataGenerator.php
        Commands/
          CreateCommandGenerator.php
          CreateHandlerGenerator.php
          UpdateCommandGenerator.php
          UpdateHandlerGenerator.php
          DeleteCommandGenerator.php
          DeleteHandlerGenerator.php
        Queries/
          ListQueryGenerator.php
          ListHandlerGenerator.php
          FindByIdQueryGenerator.php
          FindByIdHandlerGenerator.php
      Infrastructure/
        RepositoryGenerator.php
        CacheWarmerGenerator.php
      Presentation/
        ControllerGenerator.php
        OpenApiGenerator.php
        ListRequestGenerator.php
        StoreRequestGenerator.php
        UpdateRequestGenerator.php
      Providers/
        ServiceProviderGenerator.php
      Database/
        MigrationGenerator.php
      Tests/
        RepositoryTestGenerator.php
        ApiTestGenerator.php
```

**Инвариант:** `MakeDomainCommand::handle()` не содержит ни одной строки, генерирующей PHP-код. Только: парсинг аргументов → создание `DomainContext` → вызов генераторов → регистрация провайдера → `migrate` (один раз).

**Что НЕ меняется:** публичный интерфейс команды (`make:domain Name fields... --flags`) остаётся идентичным, все существующие тесты должны пройти без изменений.

---

#### Шаги

**Фундамент**

- [ ] 1.1 `GeneratorInterface` — `generate(DomainContext $ctx, Filesystem $files): void`
- [ ] 1.2 `DomainContext` — readonly DTO (name, ns, table, plural, fields, basePath, unitTestPath, featureTestPath, withElasticsearch, withCacheWarmer)
- [ ] 1.3 `FieldParser` — вынести `parseFields()`, `inferType()`, `typeDefinition()`, `fakerForField()`
- [ ] 1.4 `TestValueHelper` — вынести `testValueFor()`

**Domain-генераторы**

- [ ] 2.1 `ModelGenerator`
- [ ] 2.2 `FactoryGenerator`
- [ ] 2.3 `EventGenerator` (генерирует Created / Updated / Deleted в одном классе)
- [ ] 2.4 `NotFoundExceptionGenerator`

**Application / Data**

- [ ] 3.1 `CreateDataGenerator`
- [ ] 3.2 `UpdateDataGenerator`
- [ ] 3.3 `ResourceGenerator`
- [ ] 3.4 `FilterDataGenerator`

**Application / Commands**

- [ ] 4.1 `CreateCommandGenerator`
- [ ] 4.2 `CreateHandlerGenerator`
- [ ] 4.3 `UpdateCommandGenerator`
- [ ] 4.4 `UpdateHandlerGenerator`
- [ ] 4.5 `DeleteCommandGenerator`
- [ ] 4.6 `DeleteHandlerGenerator`

**Application / Queries**

- [ ] 5.1 `ListQueryGenerator`
- [ ] 5.2 `ListHandlerGenerator`
- [ ] 5.3 `FindByIdQueryGenerator`
- [ ] 5.4 `FindByIdHandlerGenerator`

**Infrastructure**

- [ ] 6.1 `RepositoryGenerator`
- [ ] 6.2 `CacheWarmerGenerator`

**Presentation**

- [ ] 7.1 `ControllerGenerator` (исправить B-1: добавить `;` к `assert`)
- [ ] 7.2 `ListRequestGenerator`
- [ ] 7.3 `StoreRequestGenerator`
- [ ] 7.4 `UpdateRequestGenerator`
- [ ] 7.5 `OpenApiGenerator`

**Providers**

- [ ] 8.1 `ServiceProviderGenerator`

**Database**

- [ ] 9.1 `MigrationGenerator` (убрать `$this->call('migrate')` внутри — исправить B-2)

**Tests**

- [ ] 10.1 `RepositoryTestGenerator`
- [ ] 10.2 `ApiTestGenerator`

**Оркестрация**

- [ ] 11.1 Переписать `MakeDomainCommand::handle()` — только оркестрация, удалить все `stub*`-методы и `$this->ns` (исправить B-3)
- [ ] 11.2 Убедиться, что `composer pre-push` проходит полностью

---

### Мелкие технические долги (после аудита 2026-06-08)

- [x] `QueryHandlerInterface::handle(): object|null` — убрать `|null`, привести к `object` ✓
- [x] `MakeDomainCommand::stubController()` — добавить `assert($id !== null)` после `dispatch()` в `store()` ✓
- [x] `LogoutHandler` — перенести `PersonalAccessToken::whereKey()->delete()` в `UserRepositoryInterface::deleteToken(int $id): void` (Application не должен знать про Sanctum)
- [x] `UserRepositoryInterface::list()` — интерфейс уже использует `Data`, override из `UserRepository` удалён, `@phpstan-ignore` убран ✓
- [x] ADR — зафиксировать ~10 решений из `docs/state.md` без ADR (Auth→User слияние, `UserDeleted` без `SerializesModels`, async ES-sync, `UserNotFoundException`→404 и др.)
- [x] `composer.json` — убрать `@no_additional_args` из scripts (нестандартный токен, передаётся артизану буквально)

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
