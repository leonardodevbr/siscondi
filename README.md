# SISCONDI - Sistema de Concessão de Diárias

Sistema governamental para gestão de solicitações de diárias de servidores públicos municipais.

## 📋 Sobre o Sistema

O SISCONDI é um sistema completo para gerenciar todo o fluxo de concessão de diárias para servidores públicos, desde a solicitação até o pagamento, passando por validação e autorização.

### Funcionalidades Principais

- **Gestão de Legislações**: Cadastro de cargos e valores de diárias definidos em lei
- **Cadastro de Servidores**: Registro completo dos funcionários públicos com dados pessoais e bancários
- **Solicitações de Diárias**: Criação e acompanhamento de pedidos de diárias
- **Fluxo de Aprovação**: Sistema de workflow com 4 etapas:
  1. **Solicitação** (Requerente)
  2. **Validação** (Secretário)
  3. **Autorização** (Prefeito)
  4. **Pagamento** (Tesoureiro)
- **Relatórios**: Geração de relatórios e documentos para auditoria

## 👥 Perfis de Acesso

### 1. Admin
- Acesso total ao sistema
- Gerenciamento de usuários e configurações

### 2. Requerente
- Cria solicitações de diárias
- Acompanha suas próprias solicitações

### 3. Validador (Secretário)
- Valida solicitações da sua secretaria
- Gerencia servidores lotados na secretaria

### 4. Concedente (Prefeito)
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

#### branches
Secretarias municipais (departamentos)

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
cd frontend

# Instalar dependências
npm install

# Desenvolvimento
npm run dev

# Build para produção
npm run build
```

## 🔐 Usuário Padrão

Após executar os seeders, será criado um usuário admin:

- **Email**: admin@siscondi.gov.br
- **Senha**: password

## 📝 API Endpoints

### Autenticação
- `POST /api/login` - Login
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

### Solicitações de Diárias
- `GET /api/daily-requests` - Listar
- `POST /api/daily-requests` - Criar
- `GET /api/daily-requests/{id}` - Detalhes
- `PUT /api/daily-requests/{id}` - Atualizar
- `DELETE /api/daily-requests/{id}` - Deletar
- `POST /api/daily-requests/{id}/validate` - Validar (Secretário)
- `POST /api/daily-requests/{id}/authorize` - Autorizar (Prefeito)
- `POST /api/daily-requests/{id}/pay` - Pagar (Tesoureiro)
- `POST /api/daily-requests/{id}/cancel` - Cancelar

### Secretarias
- `GET /api/branches` - Listar secretarias

### Dashboard
- `GET /api/dashboard` - Estatísticas gerais

## 🧪 Testes

```bash
# Executar todos os testes
php artisan test

# Executar testes específicos
php artisan test --filter=DailyRequestTest
```

## 📄 Licença

Este sistema é proprietário e de uso exclusivo para órgãos públicos municipais.

## 👨‍💻 Desenvolvimento

Desenvolvido por LeoonTech

---

**SISCONDI** - Sistema de Concessão de Diárias © 2026
