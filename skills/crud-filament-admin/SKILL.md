---
name: crud-filament-admin
description: >
  Padrão completo para criar um módulo CRUD no painel administrativo Filament do projeto,
  usando Sports como exemplo. Cobre Migration, Model (BaseModel + softDeletes), PermissionSeeder
  (5 permissões view/create/edit/update/delete), Policy (BasePolicy + resourceCode) registrada
  no AppServiceProvider, Seeder, Filament Resource (Resource + Pages + Schemas + Tables),
  Report de export Excel (BaseReport) registrado no ExportController e teste unitário do model.
  Use ao criar qualquer nova entidade/catálogo no admin Filament (ex.: Sport, PaymentForm, FieldType).
---

# Guia de CRUD — Exemplo: Sports

Este documento descreve o processo completo para criar um novo módulo CRUD no projeto,
usando **Sports** como exemplo prático. Siga os mesmos passos para qualquer nova entidade.

---

## 1. Migration

Crie o arquivo em `database/migrations/` com a nomenclatura `YYYY_MM_DD_NNNNNN_create_{table}_table.php`.

> O projeto usa um sequencial fixo `YYYY_MM_DD_` — o sufixo numérico deve respeitar a ordem.

```php
// database/migrations/2026_07_30_000003_create_sports_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sports');
    }
};
```

### Índices

- `name` com `->unique()` → cria índice automaticamente.
- `deleted_at` com `$table->index('deleted_at')` → obrigatório para toda tabela com `softDeletes`.

---

## 2. Model

Crie em `app/Models/`.

```php
// app/Models/Sport.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends BaseModel
{
    protected $fillable = [
        'name',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
```

### Pontos importantes

| Item | Explicação |
|------|-----------|
| `extends BaseModel` | Fornece `HasFactory`, `SoftDeletes`, `LogsActivity`, prevenção de lazy-loading. |
| `$fillable` | Sempre declarar os campos que podem ser preenchidos em massa. |
| Relations | Declarar `HasMany`, `BelongsTo`, etc. conforme o modelo de dados. |
| `IsSystemRecord` | **Não** usar em tabelas de catálogo. Esse trait adiciona `SystemRecordScope` que filtra por `is_super_admin` — coluna que só existe em `permissions`. |

---

## 3. PermissionSeeder

Adicione 5 permissões (view, create, edit, update, delete) em `database/seeders/PermissionSeeder.php`.

```php
// Sports
['name' => 'Ver Esportes',       'code' => 'sport.view',   'group' => 'Esportes'],
['name' => 'Criar Esportes',     'code' => 'sport.create', 'group' => 'Esportes'],
['name' => 'Editar Esportes',    'code' => 'sport.edit',   'group' => 'Esportes'],
['name' => 'Atualizar Esportes', 'code' => 'sport.update', 'group' => 'Esportes'],
['name' => 'Deletar Esportes',   'code' => 'sport.delete', 'group' => 'Esportes'],
```

### Convenção de código

```
{model_snake_case}.{action}
```

Ex: `sport.view`, `payment_form.create`, `field_type.delete`.

---

## 4. Policy

Crie em `app/Policies/`. Apenas define o `resourceCode()` — todo o restante vem do `BasePolicy`.

```php
// app/Policies/SportPolicy.php

<?php

namespace App\Policies;

class SportPolicy extends BasePolicy
{
    protected function resourceCode(): string
    {
        return 'sport';
    }
}
```

O `resourceCode` deve bater com o prefixo usado no `PermissionSeeder` (ex: `sport.view` → resourceCode `sport`).

### Registrar no AppServiceProvider

No arquivo `app/Providers/AppServiceProvider.php`:

1. **Import** do model e da policy no topo:

```php
use App\Models\Sport;
use App\Policies\SportPolicy;
```

2. **Registrar** no array `$policies`:

```php
protected $policies = [
    // ...
    Sport::class => SportPolicy::class,
    // ...
];
```

---

## 5. Seeder

Crie em `database/seeders/`.

```php
// database/seeders/SportsTableSeeder.php

<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportsTableSeeder extends Seeder
{
    public function run(): void
    {
        $sports = [
            'Futebol',
        ];

        foreach ($sports as $sport) {
            Sport::create(['name' => $sport]);
        }
    }
}
```

### Registrar no DatabaseSeeder

Adicione a chamada em `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ...
    // Catálogos
    SportsTableSeeder::class,
    // ...
]);
```

---

## 6. Filament Resource

### Estrutura de diretórios

Cada resource vive em `app/Filament/Resources/{Name}/` com 3 subdiretórios:

```
app/Filament/Resources/Sports/
├── SportResource.php
├── Pages/
│   ├── ListSports.php
│   ├── CreateSport.php
│   ├── EditSport.php
│   └── ViewSport.php
├── Schemas/
│   ├── SportForm.php
│   └── SportInfolist.php
└── Tables/
    └── SportsTable.php
```

### 6.1 Resource principal

```php
// app/Filament/Resources/Sports/SportResource.php

<?php

namespace App\Filament\Resources\Sports;

use App\Filament\BaseResource;
use App\Filament\Resources\Sports\Pages\CreateSport;
use App\Filament\Resources\Sports\Pages\EditSport;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Filament\Resources\Sports\Pages\ViewSport;
use App\Filament\Resources\Sports\Schemas\SportForm;
use App\Filament\Resources\Sports\Schemas\SportInfolist;
use App\Filament\Resources\Sports\Tables\SportsTable;
use App\Models\Sport;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SportResource extends BaseResource
{
    protected static ?string $model = Sport::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $modelLabel = 'Esporte';

    protected static ?string $pluralModelLabel = 'Esportes';

    protected static ?string $navigationLabel = 'Esportes';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSports::route('/'),
            'create' => CreateSport::route('/create'),
            'view'   => ViewSport::route('/{record}'),
            'edit'   => EditSport::route('/{record}/edit'),
        ];
    }
}
```

### 6.2 Form Schema

```php
// app/Filament/Resources/Sports/Schemas/SportForm.php

<?php

namespace App\Filament\Resources\Sports\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Dados do Esporte')
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),
            ]);
    }
}
```

### 6.3 Infolist Schema

```php
// app/Filament/Resources/Sports/Schemas/SportInfolist.php

<?php

namespace App\Filament\Resources\Sports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dados do Esporte')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),

                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Atualizado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
```

### 6.4 Table Schema

```php
// app/Filament/Resources/Sports/Tables/SportsTable.php

<?php

namespace App\Filament\Resources\Sports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),
                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### 6.5 Pages

**List** — `app/Filament/Resources/Sports/Pages/ListSports.php`

```php
<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Filament\Resources\Sports\SportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSports extends ListRecords
{
    protected static string $resource = SportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
```

**Create** — `app/Filament/Resources/Sports/Pages/CreateSport.php`

```php
<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Filament\Resources\Sports\SportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSport extends CreateRecord
{
    protected static string $resource = SportResource::class;
}
```

**Edit** — `app/Filament/Resources/Sports/Pages/EditSport.php`

```php
<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Filament\Resources\Sports\SportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSport extends EditRecord
{
    protected static string $resource = SportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon(Heroicon::OutlinedEye),
            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
        ];
    }
}
```

**View** — `app/Filament/Resources/Sports/Pages/ViewSport.php`

```php
<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Filament\Resources\Sports\SportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSport extends ViewRecord
{
    protected static string $resource = SportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon(Heroicon::OutlinedPencil),
            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
        ];
    }
}
```

---

## 7. Report (Export Excel)

Crie em `app/Filament/Reports/{Name}/`.

```php
// app/Filament/Reports/Sport/SportReport.php

<?php

namespace App\Filament\Reports\Sport;

use App\Filament\Reports\Common\BaseReport;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Model;

class SportReport extends BaseReport
{
    public function title(): string
    {
        return 'Relatório de Esportes';
    }

    public function headers(): array
    {
        return ['Nome', 'Criado em', 'Atualizado em'];
    }

    public function searchableFields(): array
    {
        return ['name'];
    }

    public function modelClass(): string
    {
        return Sport::class;
    }

    public function mapRow(Model $record): array
    {
        return [
            $record->name,
            $record->created_at?->format('d/m/Y H:i'),
            $record->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
```

### Registrar no ExportController

Em `app/Http/Controllers/ExportController.php`:

1. **Import** no topo:

```php
use App\Filament\Reports\Sport\SportReport;
```

2. **Adicionar case** no `match`:

```php
$report = match ($resourcePath) {
    // ...
    'sports' => new SportReport,
    // ...
};
```

A chave (`sports`) é o nome da rota do resource em snake_case plural.

---

## 8. Teste Unitário

Crie em `tests/Unit/Models/`.

```php
// tests/Unit/Models/SportTest.php

<?php

namespace Tests\Unit\Models;

use App\Models\Sport;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportTest extends TestCase
{
    use RefreshDatabase;

    protected function sport(array $attributes = []): Sport
    {
        return Sport::create(array_merge([
            'name' => 'Futebol',
        ], $attributes));
    }

    public function test_can_create_sport(): void
    {
        $sport = $this->sport();

        $this->assertDatabaseHas('sports', [
            'id' => $sport->id,
            'name' => 'Futebol',
        ]);
    }

    public function test_can_read_sport(): void
    {
        $sport = $this->sport();

        $found = Sport::find($sport->id);

        $this->assertNotNull($found);
        $this->assertSame('Futebol', $found->name);
    }

    public function test_can_update_sport(): void
    {
        $sport = $this->sport();

        $sport->update(['name' => 'Basquete']);

        $this->assertDatabaseHas('sports', [
            'id' => $sport->id,
            'name' => 'Basquete',
        ]);
    }

    public function test_can_delete_sport(): void
    {
        $sport = $this->sport();

        $sport->delete();

        $this->assertSoftDeleted('sports', ['id' => $sport->id]);
        $this->assertNull(Sport::find($sport->id));
    }

    public function test_sport_is_a_global_catalog_without_company(): void
    {
        $sport = $this->sport();

        $this->assertFalse(
            array_key_exists('company_id', $sport->getAttributes()),
            'Esporte é catálogo global e não deve ter company_id.'
        );
    }

    public function test_sport_name_is_unique(): void
    {
        $this->sport(['name' => 'Futebol']);

        $this->expectException(QueryException::class);

        Sport::create(['name' => 'Futebol']);
    }

    public function test_sport_has_positions_relationship(): void
    {
        $sport = $this->sport();

        $this->assertInstanceOf(
            HasMany::class,
            $sport->positions()
        );
    }
}
```

### Executar

```bash
php artisan test tests/Unit/Models/SportTest.php
```

### Tenancy (obrigatório quando o model pertence à empresa)

O exemplo de `Sport` é catálogo global (sem `company_id`), então não exige isolamento entre empresas.

Para qualquer model com `company_id` (ex.: módulos operacionais da Company), criar teste de tenancy cobrindo isolamento entre empresas.

Trait reutilizável disponível em:

- `tests/Feature/Support/AssertsTenantIsolation.php`

Template sugerido:

```php
use Tests\Feature\Support\AssertsTenantIsolation;

class YourModelTenantTest extends TestCase
{
    use AssertsTenantIsolation;

    public function test_tenant_isolation_blocks_cross_company_access(): void
    {
        // Arrange: crie tenant user da empresa A, registro da empresa B e registro da empresa A.

        $this->assertTenantListIsolation(
            tenantUser: $tenantUserA,
            endpoint: '/api/v1/company/your-models',
            visibleFragment: ['id' => $recordFromA->id],
            hiddenFragment: ['id' => $recordFromB->id],
        );

        $this->assertTenantCannotAccessForeignRecord(
            tenantUser: $tenantUserA,
            baseEndpoint: '/api/v1/company/your-models',
            foreignRecordId: $recordFromB->id,
            updatePayload: ['name' => 'Tentativa indevida'],
        );
    }
}
```

Cobertura mínima esperada para tenancy:

- usuário da empresa A não acessa registro da empresa B
- usuário da empresa A não lista registros da empresa B
- operações de update/delete em registro de outra empresa devem ser bloqueadas

---

## Checklist — Ordem de criação

| # | Passo | Arquivo(s) |
|---|-------|-----------|
| 1 | Migration | `database/migrations/YYYY_MM_DD_NNNNNN_create_{table}_table.php` |
| 2 | Model | `app/Models/{Name}.php` |
| 3 | Permissions | `database/seeders/PermissionSeeder.php` (adicionar bloco) |
| 4 | Policy | `app/Policies/{Name}Policy.php` |
| 5 | Registrar Policy | `app/Providers/AppServiceProvider.php` (import + `$policies`) |
| 6 | Seeder | `database/seeders/{Name}TableSeeder.php` |
| 7 | Registrar Seeder | `database/seeders/DatabaseSeeder.php` (adicionar `$this->call`) |
| 8 | Filament Resource | `app/Filament/Resources/{Names}/` (8 arquivos) |
| 9 | Report | `app/Filament/Reports/{Name}/{Name}Report.php` |
| 10 | Registrar Report | `app/Http/Controllers/ExportController.php` (import + case) |
| 11 | Teste | `tests/Unit/Models/{Name}Test.php` |
| 12 | Teste de tenancy (se houver `company_id`) | `tests/Feature` ou `tests/Unit` com cenário de isolamento entre empresas |

---

## Convenções

| Item | Convenção |
|------|----------|
| Nome da migration | `YYYY_MM_DD_NNNNNN_create_{snake_plural}_table.php` |
| Nome do model | `StudlySingular` (ex: `Sport`, `PaymentForm`) |
| Nome da tabela | `snake_plural` (ex: `sports`, `payment_forms`) |
| Nome do seeder | `{StudlyPlural}TableSeeder` (ex: `SportsTableSeeder`) |
| Nome da policy | `{StudlySingular}Policy` (ex: `SportPolicy`) |
| Namespace Filament | `App\Filament\Resources\{StudlyPlural}` (ex: `Sports`) |
| Namespace Report | `App\Filament\Reports\{StudlySingular}` (ex: `Sport`) |
| Nome do teste | `tests/Unit/Models/{StudlySingular}Test.php` |
| resourceCode | `snake_singular` (ex: `sport`, `payment_form`) |
| Navigation group | `Catálogos` para tabelas de tipo/catálogo |
| Icones | `Heroicon::Outlined*` — todos outlined |
| Tabelas com softDeletes | Sempre adicionar `$table->index('deleted_at')` na migration |
| Catálogos globais | Model usa `IsSystemRecord`, **sem** coluna `company_id` |
| Datas | Formato `d/m/Y H:i` em tabelas, infolists e reports |
