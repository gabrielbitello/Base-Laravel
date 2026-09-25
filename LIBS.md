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

### Larastan (^3.12) `dev`
Análise estática com consciência do Laravel (PHPStan). Configuração em `phpstan.neon` (nível 5).

```bash
vendor/bin/phpstan analyse
```

Roda automaticamente no workflow de CI (`.github/workflows/quality.yaml`).

---

## 🟨 Dependências Node

| Pacote | Versão | Uso |
|--------|--------|-----|
| vite | ^8.3 | Build dos assets com `laravel-vite-plugin` |
| tailwindcss | ^4.3 | CSS utilitário (plugin `@tailwindcss/vite`) |
| axios | ^1.14 \|\| !1.14.1 | Cliente HTTP no frontend (`resources/js/bootstrap.js`); a exclusão `!1.14.1` impede re-lock de uma versão com defeito |

**Comandos:** `composer dev` sobe servidor, queue, logs (Pail) e Vite via `php artisan dev`.
