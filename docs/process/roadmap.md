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

- [x] 1.1 `GeneratorInterface` — `generate(DomainContext $ctx, Filesystem $files): void`
- [x] 1.2 `DomainContext` — readonly DTO (name, ns, table, plural, fields, basePath, unitTestPath, featureTestPath, withElasticsearch, withCacheWarmer)
- [x] 1.3 `FieldParser` — вынести `parseFields()`, `inferType()`, `typeDefinition()`, `fakerForField()`
- [x] 1.4 `TestValueHelper` — вынести `testValueFor()`

**Domain-генераторы**

- [x] 2.1 `ModelGenerator`
- [x] 2.2 `FactoryGenerator`
- [x] 2.3 `EventGenerator` (генерирует Created / Updated / Deleted в одном классе)
- [x] 2.4 `NotFoundExceptionGenerator`

**Application / Data**

- [x] 3.1 `CreateDataGenerator`
- [x] 3.2 `UpdateDataGenerator`
- [x] 3.3 `ResourceGenerator`
- [x] 3.4 `FilterDataGenerator`

**Application / Commands**

- [x] 4.1 `CreateCommandGenerator`
- [x] 4.2 `CreateHandlerGenerator`
- [x] 4.3 `UpdateCommandGenerator`
- [x] 4.4 `UpdateHandlerGenerator`
- [x] 4.5 `DeleteCommandGenerator`
- [x] 4.6 `DeleteHandlerGenerator`

**Application / Queries**

- [x] 5.1 `ListQueryGenerator`
- [x] 5.2 `ListHandlerGenerator`
- [x] 5.3 `FindByIdQueryGenerator`
- [x] 5.4 `FindByIdHandlerGenerator`

**Infrastructure**

- [x] 6.1 `RepositoryGenerator`
- [x] 6.2 `CacheWarmerGenerator`

**Presentation**

- [x] 7.1 `ControllerGenerator` (исправить B-1: добавить `;` к `assert`)
- [x] 7.2 `ListRequestGenerator`
- [x] 7.3 `StoreRequestGenerator`
- [x] 7.4 `UpdateRequestGenerator`
- [x] 7.5 `OpenApiGenerator`

**Providers**

- [x] 8.1 `ServiceProviderGenerator`

**Database**

- [x] 9.1 `MigrationGenerator` (убрать `$this->call('migrate')` внутри — исправить B-2)

**Tests**

- [x] 10.1 `RepositoryTestGenerator`
- [x] 10.2 `ApiTestGenerator`

**Оркестрация**

- [x] 11.1 Переписать `MakeDomainCommand::handle()` — только оркестрация, удалить все `stub*`-методы и `$this->ns` (исправить B-3)
- [x] 11.2 `MakeDomainCommandTest` — 27/27 passed ✓

---

### Post-refactor: баги и улучшения по ревью (2026-06-12)

Полное ревью рефактора `MakeDomainCommand` выявило 15 находок. Сгруппированы по приоритету.

#### Высокий приоритет — ломают генерируемый код

- [ ] **R-1** `TestValueHelper::valueFor()` — нет ветки для `phpType='array'` → возвращает `'Test Meta'` вместо `[]` → тест с json-полем падает (RepositoryTestGenerator строка 21, ApiTestGenerator строка 29)
- [ ] **R-2** `ControllerGenerator` — заменить `assert($id !== null)` на `throw new \RuntimeException(...)`: `zend.assertions=-1` в продакшене делает assert no-op, null проходит дальше в `FindByIdQuery`
- [ ] **R-3** `MakeDomainCommand::appendRoutes()` — добавить проверку `if (! $this->files->exists($file)) return;` или создать файл: `files->get()` бросает `FileNotFoundException` если `routes/api.php` отсутствует

#### Средний приоритет — тихие неверные результаты

- [ ] **R-4** `MakeDomainCommand::formatGenerated()` — заменить `preg_replace('/(?<=\w)([A-Z])/', '_$1', $ctx->name)` на `$ctx->table`: glob не найдёт миграции с нерегулярным множественным (Category→categories, Person→people) и с аббревиатурами (SKUItem→s_k_u_item vs skuitems)
- [ ] **R-5** `ApiTestGenerator` — заменить `$data[1]['id'] ?? PHP_INT_MAX` / `?? 0` на явную проверку `assertGreaterThan(1, count($data))` перед sort-assertions: fallback делает тесты тривиально зелёными при одном элементе
- [ ] **R-6** `MakeDomainCommand::registerProvider()` — заменить `str_contains($content, $shortName.'::class')` на `str_contains($content, $providerClass.'::class')` (полный FQCN): короткое имя `UserServiceProvider` является подстрокой `OldUserServiceProvider::class`
- [ ] **R-7** `TestValueHelper::valueFor()` и `FieldParser::fakerForField()` — синхронизировать таблицы типов: `fakerForField` знает про `address/city/country/url/title/description`, `valueFor()` не знает → тест фильтрации по `city` генерирует две одинаковые записи `'Test City'` → `assertSame(1, total())` всегда падает

#### Низкий приоритет — технический долг

- [ ] **R-8** Вынести Elasticsearch-блок (`$esImports/$esInterface/$esTrait`) из `ModelGenerator` и `RepositoryGenerator` в protected-метод `AbstractGenerator` или отдельный трейт — 3 строки дублируются байт-в-байт
- [ ] **R-9** `MigrationGenerator::generate()` — использовать `$this->writeFile()` вместо прямого `$files->put()`: единственный генератор, обходящий `AbstractGenerator::writeFile()` — dry-run и будущее логирование его не затронут
- [ ] **R-10** `MakeDomainCommand::runningUnderPhpUnit()` — задокументировать или откатить семантическое расхождение с `app()->environment('testing')`: `class_exists('PHPUnit\Framework\TestCase', false)` ≠ `APP_ENV=testing`, меняет поведение migrate и pint в нестандартных окружениях
- [ ] **R-11** Восстановить вывод команды: `AbstractGenerator::writeFile()`, `appendRoutes()`, `registerProvider()` — вернуть `warn()` при skip и `line()` при создании; сейчас повторный запуск `make:domain` молча не выдаёт ни одного сообщения о пропущенных файлах
- [ ] **R-12** `MakeDomainCommand::registerProvider()` — заменить `str_replace('];', ...)` на regex-замену с якорем конца массива: текущий `str_replace` заменит все вхождения `];` в `bootstrap/providers.php` при наличии вложенных массивов
- [ ] **R-13** `FieldParser::typeDefinition()` для `timestamp/datetime` — установить `nullable=true` или убедиться, что faker-значение совместимо с MySQL strict mode: `$table->timestamp()` NOT NULL + `fake()->dateTime()->format(...)` → `Incorrect datetime value` при включённом `STRICT_TRANS_TABLES`
- [ ] **R-14** `ControllerGenerator` — объединить `$createArgs` и `$updateArgs` в одну переменную: оба цикла производят идентичный результат
- [ ] **R-15** `MakeDomainCommand::createDirectories()` — захардкоженный список из 15 путей дублирует знание о структуре каждого генератора; при добавлении нового генератора нужно помнить про правку двух файлов

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
