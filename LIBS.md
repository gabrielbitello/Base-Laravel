# 📚 Guia de Bibliotecas - Base Laravel

## 🐘 Dependências PHP

### Filament (^5.8)
Painel administrativo moderno para Laravel.

**Uso no projeto:**
```
app/Providers/Filament/     → Configuração dos painéis
app/Filament/Resources/     → Resources (CRUD)
app/Filament/Pages/         → Páginas customizadas
app/Filament/Widgets/       → Widgets do dashboard
```

**Acesso:** `/admin`

---

### Laravel Boost (^2.10)
Assistente de IA do Laravel que acelera o desenvolvimento fornecendo contexto e estrutura essenciais para gerar código de alta qualidade.

**Ferramentas disponíveis:**
- **Application Info** - Lê versões PHP/Laravel, pacotes e modelos Eloquent
- **Database Schema** - Inspeciona o schema completo do banco de dados
- **Database Queries** - Executa queries diretamente do assistente de IA
- **Route Inspector** - Analisa as rotas da aplicação
- **Artisan Commands** - Lista e inspeciona comandos Artisan
- **Tinker Integration** - Executa código no contexto da aplicação
- **Configuration Access** - Acessa valores de configuração
- **Documentation Search** - Busca na documentação do Laravel
- **Error Tracking** - Lê logs e rastreia erros

**Instalação:**
```bash
composer require laravel/boost --dev
php artisan boost:install
```

**Documentação:** https://laravel.com/ai/boost

---

### Laravel Pao (^1.1) `dev`
Saída otimizada para agentes de IA nas ferramentas de teste PHP (PHPUnit/Pint etc.). Detecta quando um agente está executando os testes e formata a saída de forma estruturada e legível por máquina.

---

### Laravel Debugbar (^4.4) `dev`
Barra de debug no navegador em ambiente local (`APP_DEBUG=true`): queries, tempo de execução, memória, views renderizadas e mais. Não ativa em produção.

---

### Laravel IDE Helper (^3.7) `dev`
Gera arquivos de helpers para a IDE (autocompletar de facades e modelos Eloquent no PhpStorm).

```bash
php artisan ide-helper:generate        # helpers de facades
php artisan ide-helper:models -W       # docblocks nos modelos
```

Arquivos gerados (`_ide_helper.php`, `_ide_helper_models.php`, `.phpstorm.meta.php`) estão no `.gitignore`.

---

### Filament Developer Logins (^2.1) `dev`
Botões de login rápido na tela de `/admin/login` para os usuários pré-configurados do seeder — evita digitar e-mail/senha no dia a dia local. Também permite trocar de conta logado (`switchable`).

**Só ativo em ambiente `local`/`development`** (`app()->environment()`); em staging/produção nem o botão nem a rota existem.

Os usuários oferecidos são configurados em `app/Providers/Filament/AdminPanelProvider.php` e precisam existir no banco (o `DatabaseSeeder` cria o Admin em local).

---

### Filament Shield (^4.3) + spatie/laravel-permission (^8.3)
RBAC pronto: papéis e permissões com UI no painel (Shield) e base spatie. O `User` já usa `HasRoles`, o plugin está registrado e o `Gate::before` no `AppServiceProvider` dá bypass total ao papel `super_admin`.

Ao iniciar um projeto real: `php artisan shield:install admin` (gera papéis/permissões dos resources e o super admin).

---

### spatie/laravel-activitylog (^5.1) + pxlrbt/filament-activity-log (^3.1)
Auditoria de mudanças em modelos com visualização no painel. O `User` já usa `LogsActivity` + `CausesActivity` (tabela `activity`).

Atenção: no activitylog v5 os traits ficaram em `Spatie\Activitylog\Models\Concerns\`. Para ver o log de um resource, crie uma página estendendo `pxlrbt\FilamentActivityLog\Pages\ListActivities` e registre em `getPages()`.

---

### lucascudo/laravel-pt-br-localization (^3.0)
Mensagens de validação, auth, passwords e paginação em pt-BR (`lang/pt_BR`). O template já inicia com `APP_LOCALE=pt_BR` e `faker_locale pt_BR` (Filament traduz o painel automaticamente para pt_BR).

---

### Filament JSON Column (^4.1)
Campo/coluna JSON para Filament (editor no form, visualização na tabela). Uso direto: `JsonColumn::make('metadata')`.

---

### Campos brasileiros (nativo, sem lib)
O `leandrocfe/filament-ptbr-form-fields` não tem release para Filament 5 — use o Filament nativo:

```php
TextInput::make('cpf')->mask('000.000.000-00');
TextInput::make('cnpj')->mask('00.000.000/0000-00');
TextInput::make('phone')->tel()->mask('(00) 00000-0000');
TextInput::make('price')->prefix('R$')->numeric();
```

---

### Paratest (^7.20) `dev`
Testes em paralelo: `php artisan test --parallel`.

### Roave Security Advisories (dev)
Bloqueia `composer update/install` se alguma versão resolvida tiver CVE conhecida. Se um update falhar com conflito de advisory, é sinal para revisar a dependência.

---

## 🌐 Tooling de Traduções

Dicionário de strings em `lang/pt_BR.json` (chave = texto em inglês usado via `__('...')`), com três camadas de automação:

- **`make translate KEY="Log in" PT="Entrar"`** — insere/atualiza a chave em ordem alfabética, preservando o arquivo. `UPDATE=1` substitui valor existente. (script: `scripts/add-translation.mjs`)
- **`make missing-translations`** — lista chaves `__()` usadas nas views sem tradução (`--json`/`--verbose` disponíveis). (script: `scripts/find-missing-translations.php`)
- **pre-commit** (`.githooks/pre-commit`) — normaliza o `lang/*.json` staged: valida JSON, ordena chaves, 4 espaços, unicode literal.
- **Merge semântico** — `lang/*.json` usa o driver `merge=lang-json` (`.gitattributes` + registro no composer): duas branches editando traduções fazem merge sem conflito; mesmo valor alterado nos dois lados mantém o da branch atual e avisa. (`.githooks/merge-lang.php`)
- **CI `auto-fix-lang.yml`** — no PR, o bot mergeia main na branch com o driver semântico, normaliza e empurra de volta; branches antigas chegam na main sem conflito de traduções. Requer `ENABLE_TEMPLATE_WORKFLOWS=true`.

---

## 🌍 Conteúdo translatable (modelos multi-idioma)

Instalado: `spatie/laravel-translatable` (^6.14) + `lara-zeus/spatie-translatable` (^2.0) — tradução de **conteúdo de modelos** (diferente das strings de UI via `__()`).

Para usar num resource:
1. Modelo: `use Spatie\Translatable\HasTranslations;` + `public array $translatable = ['campo'];`
2. Páginas do resource: usar as concerns `LaraZeus\SpatieTranslatable\Resources\Pages\{CreateRecord,EditRecord,ListRecords,ManageRecords,ViewRecord}\Translatable` (trocam as originais) — elas adicionam o seletor de locale e o content driver.
3. Opcional: registrar `LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::make()` no painel para configurar `defaultLocales`/`useFallbackLocale`.

**Trait do template** (`App\Filament\Translatable\Concerns\InteractsWithTranslatableForms`): normaliza FileUpload (string↔array), remove chaves UUID de Repeaters (preservando KeyValue) e aplica fallback do locale padrão **só para escalares** — nunca recursivo em arrays. Uso na página:

```php
use App\Filament\Translatable\Concerns\InteractsWithTranslatableForms;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Translatable;

class EditPost extends EditRecord
{
    use Translatable;
    use InteractsWithTranslatableForms;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->translatableFill($data, $this->getRecord(), $this->activeLocale);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->translatableSave($data);
    }
}
```

Coberto por `tests/Unit/TranslatableFormsTest.php`.

**Armadilhas conhecidas** (estudo de caso vippers, trait `EditPageWithTranslations`):
1. **FileUpload em campo translatable**: o spatie guarda string, o form espera array — normalizar em `mutateFormDataBeforeFill`/`mutateFormDataBeforeSave`.
2. **KeyValue gera chaves UUID** que sujam o JSON traduzido — limpar antes de salvar.
3. **Fallback automático de locale no fill**: preencher locale vazio com o locale padrão corrompe JSON aninhado (caso real: `content.logos_1.logos`) — o vippers desativou o método (`fillFormDISABLED`, TODO). Prefira fallback por campo simples, nunca recursivo em arrays aninhados.

---

### Filament Language Switcher (^5.0)
Botão no topbar do painel para trocar o idioma — usa o `__()` nativo, sem banco de traduções. Idioma persistido **por usuário** na coluna `lang` da tabela `users` (aplicado a cada request pelo middleware do plugin). Locais disponíveis configurados em `config/filament-language-switcher.php` (pt_BR + en).

---

### Filament Spatie Settings Plugin (^5.8) + spatie/laravel-settings (^3.9)
Página **Configurações** no painel para editar settings persistidos no banco (tabela `settings`), com cache e tipagem por classe DTO.

- Exemplo incluso: `app/Settings/SiteSettings.php` (campo `site_name`) + página `app/Filament/Pages/ManageSettings.php` em `/admin/manage-settings`.
- Novo grupo de settings: crie a classe em `app/Settings/`, gere a migration de colunas com `php artisan make:settings-migration NomeDaMigration` e a página com `php artisan make:filament-settings-page` (ou copie a ManageSettings).
- Uso no código: `app(App\Settings\SiteSettings::class)->site_name`.
- Atenção (Filament 5): em páginas, tipar `protected static string|\UnitEnum|null $navigationGroup` (com barra — dentro de namespace o `UnitEnum` sem `\` resolve para um tipo inexistente).

---

### Larastan (^3.12) `dev`
Análise estática com consciência do Laravel (PHPStan). Configuração em `phpstan.neon` (nível 5). Pacote em `larastan/larastan` (o vendor antigo `nunomaduro/larastan` foi abandonado).

```bash
vendor/bin/phpstan analyse
```

Roda automaticamente no workflow de CI (`.github/workflows/quality.yaml`).

---

## 🟨 Dependências Node

| Pacote | Versão | Uso |
|--------|--------|-----|
| vite | ^8.3 | Build dos assets com `laravel-vite-plugin` |
| typescript | ^7.0 | Frontend em TypeScript (`resources/js/*.ts`); `npm run typecheck` e validação no build |
| tailwindcss | ^4.3 | CSS utilitário (plugin `@tailwindcss/vite`) |
| axios | ^1.14 \|\| !1.14.1 | Cliente HTTP no frontend (`resources/js/bootstrap.ts`); a exclusão `!1.14.1` impede re-lock de uma versão com defeito |

**Comandos:** `composer dev` sobe servidor, queue, logs (Pail) e Vite via `php artisan dev`. O build roda `tsc --noEmit` antes do Vite — erro de tipo quebra o build (e o CI).
