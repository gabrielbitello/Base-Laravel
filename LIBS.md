# 📚 Guia de Bibliotecas - BaseLaravel

## 🐘 Dependências PHP

### Filament (^5.0)
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

### Laravel Boost (^2.2)
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

