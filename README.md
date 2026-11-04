# Laravel IA

Base de um projeto Laravel com **painel administrativo Filament**, no mesmo padrão dos projetos `laravel-mail` e `laravel-wpp-boot`. Ainda não tem funcionalidades de IA: é o ponto de partida para elas.

- **Stack:** Laravel 13, PHP 8.3+, Filament 5 (Livewire 4), fila e sessão no banco de dados.
- **Painel:** `/admin`. A rota `/` redireciona para `/admin/login`.

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Abra http://localhost:8000/admin e entre com o admin do `.env`.

> O `.env.example` usa SQLite. Se trocar para MySQL/PostgreSQL, lembre que `migrate:fresh` **apaga todas as tabelas** do banco configurado.

## Configuração (.env)

| Variável | Para que serve |
|---|---|
| `APP_NAME` | Nome do painel. |
| `APP_URL` | URL em que você abre o painel. |
| `APP_LOCALE` | Idioma (`pt_BR`). |
| `APP_ADMIN_NAME` / `APP_ADMIN_MAIL` / `APP_ADMIN_PASSWORD` | Super admin criado pelo seeder. **Troque a senha fora do ambiente local.** |

O `AdminSeeder` usa `updateOrCreate` pelo e-mail: rodar de novo atualiza o admin em vez de duplicar.

```bash
php artisan db:seed --class=AdminSeeder
```

## Perfis

| Perfil | O que faz |
|---|---|
| **Usuário comum** | Entra no painel. |
| **Super admin** (`is_master`) | Entra no painel e gerencia **Administração → Usuários** (criar, editar, excluir, com filtros por perfil, e-mail verificado e data). |

A tela de Usuários só abre para o super admin (`canViewAny`, `canCreate`, `canEdit`, `canDelete`).

## Estrutura

```
app/
├── Filament/
│   ├── Filters/            Filtros das tabelas, por model (Common, User)
│   └── Resources/          Users (Resource + Pages + Schemas + Tables)
├── Models/                 BaseModel (HasFactory + SoftDeletes), User
└── Providers/Filament/AdminPanelProvider.php    Painel /admin
config/app.php              Chave `admin` (lida das variáveis APP_ADMIN_*)
database/seeders/           AdminSeeder
skills/crud-filament-admin  Guia para criar novos CRUDs no painel
```

Para criar um novo módulo no painel, siga `skills/crud-filament-admin/SKILL.md`. Convenções do projeto: models herdam de `BaseModel` (soft delete), campos em um método por linha nos forms, tabelas e infolists, e filtros em `app/Filament/Filters/<Model>/`.

## Testes

```bash
php artisan test
```

Rodam em SQLite em memória. Cobrem o login, o acesso ao painel, a tela de Usuários exclusiva do super admin, o seeder do admin e o model `User`.
# laravel_ia
