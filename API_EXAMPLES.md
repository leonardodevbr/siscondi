# 📡 SISCONDI - Exemplos de Uso da API

## 🔐 Autenticação

### Login
```bash
POST /api/login
Content-Type: application/json

{
  "email": "admin@siscondi.gov.br",
  "password": "password"
}

# Resposta
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@siscondi.gov.br",
    "roles": ["admin"],
    "permissions": [...]
  }
}
```

### Logout
```bash
POST /api/logout
Authorization: Bearer {token}
```

---

## 📊 Dashboard

### Obter Estatísticas
```bash
GET /api/dashboard
Authorization: Bearer {token}

# Resposta
{
  "total_servants": 150,
  "total_legislations": 25,
  "total_requests": 340,
  "requests_by_status": {
    "draft": 5,
    "requested": 12,
    "validated": 8,
    "authorized": 15,
    "paid": 280,
    "cancelled": 20
  },
  "financial": {
    "total_authorized": 125000.00,
    "total_paid": 110000.00,
    "pending_payment": 15000.00
  },
  "recent_requests": [...]
}
```

---

## 📜 Legislações (Cargos)

### Listar Legislações
```bash
GET /api/legislations?search=secretario&is_active=1
Authorization: Bearer {token}

# Resposta
{
  "data": [
    {
      "id": 1,
      "code": "CC-1",
      "title": "Secretário Municipal",
      "law_number": "Lei 001/2024",
      "daily_value": "350.00",
      "is_active": true,
      "created_at": "2026-01-28T10:00:00.000000Z",
      "updated_at": "2026-01-28T10:00:00.000000Z"
    }
  ]
}
```

### Criar Legislação
```bash
POST /api/legislations
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "CC-2",
  "title": "Diretor de Departamento",
  "law_number": "Lei 002/2024",
  "daily_value": 280.00,
  "is_active": true
}
```

### Atualizar Legislação
```bash
PUT /api/legislations/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "daily_value": 380.00
}
```

### Deletar Legislação
```bash
DELETE /api/legislations/1
Authorization: Bearer {token}

# Resposta (se houver servidores vinculados)
{
  "message": "Não é possível deletar uma legislação com servidores vinculados."
}
```

---

## 👥 Servidores

### Listar Servidores
```bash
GET /api/servants?search=joao&department_id=1&is_active=1
Authorization: Bearer {token}

# Resposta
{
  "data": [
    {
      "id": 1,
      "name": "João da Silva",
      "cpf": "12345678901",
      "formatted_cpf": "123.456.789-01",
      "rg": "MG1234567",
      "organ_expeditor": "SSP/MG",
      "matricula": "2024001",
      "bank_name": "Banco do Brasil",
      "agency_number": "1234",
      "account_number": "56789-0",
      "account_type": "corrente",
      "email": "joao@email.com",
      "phone": "31999999999",
      "is_active": true,
      "legislation": {
        "id": 1,
        "code": "CC-1",
        "title": "Secretário Municipal",
        "daily_value": "350.00"
      },
      "department": {
        "id": 1,
        "name": "Secretaria de Educação"
      }
    }
  ]
}
```

### Criar Servidor
```bash
POST /api/servants
Authorization: Bearer {token}
Content-Type: application/json

{
  "legislation_id": 1,
  "department_id": 1,
  "name": "Maria Santos",
  "cpf": "98765432100",
  "rg": "MG9876543",
  "organ_expeditor": "SSP/MG",
  "matricula": "2024002",
  "bank_name": "Caixa Econômica Federal",
  "agency_number": "0001",
  "account_number": "12345-6",
  "account_type": "poupanca",
  "email": "maria@email.com",
  "phone": "31988888888",
  "is_active": true
}
```

### Atualizar Servidor
```bash
PUT /api/servants/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "phone": "31977777777",
  "email": "joao.novo@email.com"
}
```

---

## 📝 Solicitações de Diárias

### Listar Solicitações
```bash
GET /api/daily-requests?status=requested&department_id=1
Authorization: Bearer {token}

# Resposta
{
  "data": [
    {
      "id": 1,
      "servant_id": 1,
      "destination_city": "Belo Horizonte",
      "destination_state": "MG",
      "departure_date": "2026-02-01",
      "return_date": "2026-02-03",
      "reason": "Reunião com SEDESE sobre projeto educacional",
      "quantity_days": "2.5",
      "unit_value": "350.00",
      "total_value": "875.00",
      "status": "requested",
      "status_label": "Solicitado",
      "status_color": "blue",
      "is_editable": true,
      "is_cancellable": true,
      "servant": {
        "id": 1,
        "name": "João da Silva",
        "cpf": "12345678901",
        "legislation": {...},
        "department": {...}
      },
      "requester": {
        "id": 2,
        "name": "Pedro Oliveira"
      },
      "created_at": "2026-01-28T14:30:00.000000Z"
    }
  ]
}
```

### Criar Solicitação
```bash
POST /api/daily-requests
Authorization: Bearer {token}
Content-Type: application/json

{
  "servant_id": 1,
  "destination_city": "Belo Horizonte",
  "destination_state": "MG",
  "departure_date": "2026-02-01",
  "return_date": "2026-02-03",
  "reason": "Reunião com SEDESE sobre projeto educacional",
  "quantity_days": 2.5
}

# Resposta
{
  "data": {
    "id": 1,
    "servant_id": 1,
    "legislation_snapshot_id": 1,
    "destination_city": "Belo Horizonte",
    "destination_state": "MG",
    "departure_date": "2026-02-01",
    "return_date": "2026-02-03",
    "reason": "Reunião com SEDESE sobre projeto educacional",
    "quantity_days": "2.5",
    "unit_value": "350.00",
    "total_value": "875.00",
    "status": "draft",
    "requester_id": 1,
    "created_at": "2026-01-28T14:30:00.000000Z"
  }
}
```

### Atualizar Solicitação (apenas se status = draft ou requested)
```bash
PUT /api/daily-requests/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "quantity_days": 3.0,
  "reason": "Reunião estendida por mais meio dia"
}
```

### Validar Solicitação (Secretário)
```bash
POST /api/daily-requests/1/validate
Authorization: Bearer {token}

# Resposta
{
  "data": {
    "id": 1,
    "status": "validated",
    "status_label": "Validado",
    "validator_id": 2,
    "validated_at": "2026-01-28T15:00:00.000000Z",
    "validator": {
      "id": 2,
      "name": "Carlos Secretário"
    }
  }
}
```

### Autorizar Solicitação (Prefeito)
```bash
POST /api/daily-requests/1/authorize
Authorization: Bearer {token}

# Resposta
{
  "data": {
    "id": 1,
    "status": "authorized",
    "status_label": "Concedido",
    "authorizer_id": 3,
    "authorized_at": "2026-01-28T16:00:00.000000Z",
    "authorizer": {
      "id": 3,
      "name": "José Prefeito"
    }
  }
}
```

### Pagar Solicitação (Tesoureiro)
```bash
POST /api/daily-requests/1/pay
Authorization: Bearer {token}

# Resposta
{
  "data": {
    "id": 1,
    "status": "paid",
    "status_label": "Pago",
    "payer_id": 4,
    "paid_at": "2026-01-28T17:00:00.000000Z",
    "payer": {
      "id": 4,
      "name": "Ana Tesoureira"
    }
  }
}
```

### Cancelar Solicitação
```bash
POST /api/daily-requests/1/cancel
Authorization: Bearer {token}

# Resposta
{
  "data": {
    "id": 1,
    "status": "cancelled",
    "status_label": "Cancelado"
  }
}
```

### Deletar Solicitação (apenas se editável)
```bash
DELETE /api/daily-requests/1
Authorization: Bearer {token}

# Resposta
{
  "message": "Solicitação deletada com sucesso."
}

# Ou erro se não puder deletar
{
  "message": "Não é possível deletar uma solicitação que já foi processada."
}
```

---

## 🏢 Secretarias

### Listar Secretarias
```bash
GET /api/branches?search=educacao
Authorization: Bearer {token}

# Resposta
{
  "data": [
    {
      "id": 1,
      "name": "Secretaria de Educação",
      "is_main": false,
      "created_at": "2026-01-28T10:00:00.000000Z",
      "updated_at": "2026-01-28T10:00:00.000000Z"
    }
  ]
}
```

---

## 🔍 Filtros e Paginação

### Filtros Disponíveis

**Legislações:**
- `?search=termo` - Busca em code, title, law_number
- `?is_active=1` - Apenas ativas
- `?all=1` - Retorna todos (sem paginação)

**Servidores:**
- `?search=termo` - Busca em name, cpf, matricula
- `?department_id=1` - Filtra por secretaria
- `?legislation_id=1` - Filtra por cargo
- `?is_active=1` - Apenas ativos

**Solicitações:**
- `?search=termo` - Busca no nome do servidor
- `?status=requested` - Filtra por status
- `?servant_id=1` - Filtra por servidor
- `?department_id=1` - Filtra por secretaria

### Paginação
```bash
GET /api/daily-requests?page=2

# Resposta
{
  "data": [...],
  "current_page": 2,
  "per_page": 15,
  "total": 45,
  "last_page": 3
}
```

---

## ⚠️ Tratamento de Erros

### Erro de Validação (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "cpf": ["O campo cpf já está sendo utilizado."],
    "email": ["O campo email deve ser um endereço de e-mail válido."]
  }
}
```

### Erro de Autorização (403)
```json
{
  "message": "This action is unauthorized."
}
```

### Erro de Autenticação (401)
```json
{
  "message": "Unauthenticated."
}
```

### Erro de Negócio (422)
```json
{
  "message": "Esta solicitação não pode ser validada no status atual."
}
```

---

## 🧪 Testando com cURL

### Exemplo Completo: Criar e Aprovar uma Solicitação

```bash
# 1. Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@siscondi.gov.br","password":"password"}' \
  | jq -r '.token')

# 2. Criar Legislação
curl -X POST http://localhost:8000/api/legislations \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "CC-1",
    "title": "Secretário Municipal",
    "law_number": "Lei 001/2024",
    "daily_value": 350.00,
    "is_active": true
  }'

# 3. Criar Servidor
curl -X POST http://localhost:8000/api/servants \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "legislation_id": 1,
    "department_id": 1,
    "name": "João da Silva",
    "cpf": "12345678901",
    "rg": "MG1234567",
    "organ_expeditor": "SSP/MG",
    "matricula": "2024001",
    "is_active": true
  }'

# 4. Criar Solicitação
curl -X POST http://localhost:8000/api/daily-requests \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "servant_id": 1,
    "destination_city": "Belo Horizonte",
    "destination_state": "MG",
    "departure_date": "2026-02-01",
    "return_date": "2026-02-03",
    "reason": "Reunião importante",
    "quantity_days": 2.5
  }'

# 5. Validar
curl -X POST http://localhost:8000/api/daily-requests/1/validate \
  -H "Authorization: Bearer $TOKEN"

# 6. Autorizar
curl -X POST http://localhost:8000/api/daily-requests/1/authorize \
  -H "Authorization: Bearer $TOKEN"

# 7. Pagar
curl -X POST http://localhost:8000/api/daily-requests/1/pay \
  -H "Authorization: Bearer $TOKEN"
```

---

## 📚 Recursos Adicionais

- Use **Postman** ou **Insomnia** para testes mais fáceis
- Todos os endpoints requerem autenticação via Bearer Token
- Respeite as permissões de cada perfil de usuário
- Consulte `README.md` para mais informações

---

**🎯 Pronto para integrar o frontend!**
