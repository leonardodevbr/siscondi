# SISCONDI - Sistema de Concessão de Diárias

Sistema governamental para gestão de solicitações de diárias de servidores públicos municipais.

## 📋 Sobre o Sistema

O SISCONDI é um sistema completo para gerenciar todo o fluxo de concessão de diárias para servidores públicos, desde a solicitação até o pagamento, passando por validação e autorização.

### Funcionalidades Principais

- **Gestão de Legislações**: Cadastro de cargos e valores de diárias definidos em lei
- **Cadastro de Servidores**: Registro completo dos funcionários públicos com dados pessoais e bancários; importação em planilha (com geração opcional de username no formato primeiro.ultimo)
- **Solicitações de Diárias**: Criação e acompanhamento de pedidos de diárias
- **Fluxo de Aprovação**: Sistema de workflow com 4 etapas:
  1. **Solicitação**
  2. **Validação**
  3. **Autorização**
  4. **Pagamento**
- **Relatórios**: Geração de relatórios e documentos para auditoria
- **Portal da Transparência**: Página pública (`/transparencia`) com consulta a diárias e passagens (dados pagos), filtros por exercício/mês/gestão/destino/servidor, uso do brasão do município e exportação CSV/impressão

## 👥 Perfis de Acesso

### 1. Admin
- Acesso total ao sistema
- Gerenciamento de usuários e configurações

### 2. Requerente
- Cria solicitações de diárias
- Acompanha suas próprias solicitações

### 3. Validador  
- Valida solicitações da sua secretaria
- Gerencia servidores lotados na secretaria

### 4. Concedente
- Autoriza/concede diárias validadas
- Gerencia legislações e secretarias
- Acesso a relatórios gerenciais

### 5. Pagador (Tesoureiro)
- Efetua pagamento de diárias autorizadas
- Acesso a relatórios financeiros

## 🏗️ Arquitetura

### Backend
- **Framework**: Laravel 10+
- **Autenticação**: Laravel Sanctum
- **ACL**: Spatie Laravel Permission
- **Database**: MySQL/PostgreSQL

### Frontend
- **Framework**: Vue.js 3
- **Build Tool**: Vite
- **Styling**: Tailwind CSS
- **Router**: Vue Router
- **State Management**: Pinia

## 📦 Estrutura de Dados

### Tabelas Principais

#### legislations
Cargos e valores definidos em lei
- `code`: Código do cargo (ex: CC-1)
- `title`: Nome do cargo
- `law_number`: Número da lei
- `daily_value`: Valor da diária

#### servants
Servidores públicos
- Dados pessoais (CPF, RG, matrícula)
- Dados bancários
- Vinculação com cargo e secretaria

#### daily_requests
Solicitações de diárias
- Informações da viagem
- Cálculo de valores
- Status do fluxo
- Auditoria (quem validou, autorizou, pagou)

#### departments
Secretarias municipais (lotação dos servidores e usuários)

## 🚀 Instalação

### Pré-requisitos
- PHP 8.1+
- Composer
- Node.js 18+
- MySQL/PostgreSQL

### Backend

```bash
# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env
php artisan key:generate

# Configurar banco de dados no .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=siscondi
# DB_USERNAME=root
# DB_PASSWORD=

# Executar migrations e seeders
php artisan migrate --seed

# Iniciar servidor
php artisan serve
```

### Frontend

```bash
# Na raiz do projeto (frontend integrado com Vite)
npm install
npm run dev    # Desenvolvimento
npm run build # Build para produção
```

## 🔐 Autenticação e Usuário Padrão

- **Login**: o sistema aceita **e-mail**, **nome de usuário (username)** ou **matrícula** + senha. Em **Configurações** (Super Admin) é possível definir quais métodos de login estão habilitados (E-mail, Usuário, Matrícula).
- Após os seeders, um usuário admin é criado:
  - **E-mail**: admin@siscondi.gov.br
  - **Senha**: password

## 📝 API Endpoints

### Autenticação
- `POST /api/login` - Login (envie `login` e `password`; `login` pode ser e-mail, username ou matrícula, conforme configuração)
- `POST /api/logout` - Logout
- `GET /api/user` - Usuário autenticado

### Legislações
- `GET /api/legislations` - Listar
- `POST /api/legislations` - Criar
- `GET /api/legislations/{id}` - Detalhes
- `PUT /api/legislations/{id}` - Atualizar
- `DELETE /api/legislations/{id}` - Deletar

### Servidores
- `GET /api/servants` - Listar
- `POST /api/servants` - Criar
- `GET /api/servants/{id}` - Detalhes
- `PUT /api/servants/{id}` - Atualizar
- `DELETE /api/servants/{id}` - Deletar
- `GET /api/servants/import/template` - Download do modelo de planilha para importação
- `POST /api/servants/import/validate` - Validar planilha (preview)
- `POST /api/servants/import` - Executar importação

### Solicitações de Diárias
- `GET /api/daily-requests` - Listar
- `POST /api/daily-requests` - Criar
- `GET /api/daily-requests/{id}` - Detalhes
- `PUT /api/daily-requests/{id}` - Atualizar
- `DELETE /api/daily-requests/{id}` - Deletar
- `POST /api/daily-requests/{id}/validate` - Validar
- `POST /api/daily-requests/{id}/authorize` - Autorizar
- `POST /api/daily-requests/{id}/pay` - Pagar
- `POST /api/daily-requests/{id}/cancel` - Cancelar

### Secretarias (departments)
- `GET /api/departments` - Listar secretarias

### Configurações (Super Admin)
- `GET /api/settings` - Listar configurações (ex.: app_name, allowed_login_methods)
- `PUT /api/settings` - Atualizar configurações

### Dashboard
- `GET /api/dashboard` - Resumo por status, financeiro e solicitações recentes

## 🧪 Testes

```bash
# Executar todos os testes
php artisan test

# Executar testes específicos
php artisan test --filter=DailyRequestTest
```

## 📄 Licença

Este sistema é proprietário e de uso exclusivo para órgãos públicos municipais.

## 📚 Documentação adicional

- **Visão geral do sistema**: `docs/OVERVIEW_SISTEMA.md` — módulos, fluxos, entidades, ajustes recentes e sugestão de módulo adicional.
- **Escopo e decisões**: `docs/ANALISE_ESCOPO_E_BRANCH.md`
- **Seeders**: `README_SEEDERS.md`

## 👨‍💻 Desenvolvimento

Desenvolvido por LeoonTech

---

**SISCONDI** - Sistema de Concessão de Diárias © 2026
