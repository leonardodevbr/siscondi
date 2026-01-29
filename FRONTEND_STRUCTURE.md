# Estrutura Frontend - SISCONDI

## 📁 Estrutura de Pastas Recomendada

```
frontend/src/
├── views/
│   ├── Auth/
│   │   └── Login.vue
│   ├── Dashboard/
│   │   └── Home.vue
│   ├── Legislations/
│   │   ├── Index.vue (Listagem)
│   │   └── Form.vue (Criar/Editar)
│   ├── Servants/
│   │   ├── Index.vue (Listagem)
│   │   └── Form.vue (Criar/Editar)
│   ├── DailyRequests/
│   │   ├── Index.vue (Listagem)
│   │   ├── Form.vue (Criar/Editar)
│   │   ├── Details.vue (Detalhes com ações)
│   │   └── MyRequests.vue (Minhas solicitações)
│   ├── Departments/ (Secretarias)
│   │   ├── Index.vue
│   │   └── Form.vue
│   ├── Users/
│   │   ├── Index.vue
│   │   └── Form.vue
│   └── Settings/
│       └── Index.vue
│
├── components/
│   ├── Common/
│   │   ├── Button.vue
│   │   ├── Input.vue
│   │   ├── SelectInput.vue
│   │   ├── Modal.vue
│   │   ├── AppLogo.vue
│   │   └── UserMenu.vue
│   ├── DailyRequests/
│   │   ├── StatusBadge.vue
│   │   ├── RequestCard.vue
│   │   ├── ApprovalTimeline.vue
│   │   └── CalculationSummary.vue
│   ├── Servants/
│   │   ├── ServantCard.vue
│   │   └── ServantSelector.vue
│   └── Layout/
│       ├── Header.vue
│       └── Sidebar.vue
│
├── stores/
│   ├── auth.js
│   ├── dailyRequests.js
│   ├── servants.js
│   ├── legislations.js
│   ├── departments.js
│   ├── users.js
│   └── settings.js
│
├── services/
│   ├── api.js (Configuração Axios)
│   ├── dailyRequestService.js
│   ├── servantService.js
│   ├── legislationService.js
│   └── departmentService.js
│
└── utils/
    ├── format.js (Formatação de CPF, valores, datas)
    └── permissions.js (Helpers de permissões)
```

## 🎨 Componentes Principais a Criar

### 1. Views/DailyRequests/Index.vue
Listagem de solicitações com:
- Filtros por status, servidor, secretaria
- Tabela com informações principais
- Badges de status coloridos
- Ações rápidas (visualizar, validar, autorizar, pagar)

### 2. Views/DailyRequests/Form.vue
Formulário de solicitação com:
- Seleção de servidor (autocomplete)
- Campos de destino e datas
- Cálculo automático de diárias
- Validação de datas

### 3. Views/DailyRequests/Details.vue
Detalhes da solicitação com:
- Timeline de aprovação
- Informações completas do servidor
- Botões de ação conforme perfil:
  - Validador: Botão "Validar"
  - Concedente: Botão "Autorizar"
  - Pagador: Botão "Pagar"
  - Todos: Botão "Cancelar" (se permitido)

### 4. Components/DailyRequests/StatusBadge.vue
Badge colorido para status:
```vue
<template>
  <span :class="statusClass">
    {{ statusLabel }}
  </span>
</template>

<script setup>
const props = defineProps(['status'])

const statusConfig = {
  draft: { label: 'Rascunho', color: 'gray' },
  requested: { label: 'Solicitado', color: 'blue' },
  validated: { label: 'Validado', color: 'yellow' },
  authorized: { label: 'Concedido', color: 'green' },
  paid: { label: 'Pago', color: 'purple' },
  cancelled: { label: 'Cancelado', color: 'red' }
}
</script>
```

### 5. Components/DailyRequests/ApprovalTimeline.vue
Timeline visual do fluxo de aprovação mostrando:
- Quem solicitou e quando
- Quem validou e quando
- Quem autorizou e quando
- Quem pagou e quando

### 6. Views/Servants/Index.vue
Listagem de servidores com:
- Filtros por secretaria, cargo, status
- Busca por nome, CPF, matrícula
- Card/Tabela com foto, nome, cargo, secretaria

### 7. Views/Servants/Form.vue
Formulário completo com abas:
- Dados Pessoais
- Dados Bancários
- Vinculação (Cargo e Secretaria)

### 8. Views/Dashboard/Home.vue
Dashboard com cards de:
- Total de servidores ativos
- Total de solicitações por status
- Valores financeiros (autorizado, pago, pendente)
- Solicitações recentes
- Ações pendentes (conforme perfil)

## 🎯 Stores (Pinia)

### dailyRequests.js
```javascript
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useDailyRequestsStore = defineStore('dailyRequests', {
  state: () => ({
    requests: [],
    currentRequest: null,
    loading: false
  }),
  
  actions: {
    async fetchRequests(filters = {}) {
      this.loading = true
      const { data } = await api.get('/daily-requests', { params: filters })
      this.requests = data.data
      this.loading = false
    },
    
    async createRequest(payload) {
      const { data } = await api.post('/daily-requests', payload)
      return data.data
    },
    
    async validateRequest(id) {
      const { data } = await api.post(`/daily-requests/${id}/validate`)
      return data.data
    },
    
    async authorizeRequest(id) {
      const { data } = await api.post(`/daily-requests/${id}/authorize`)
      return data.data
    },
    
    async payRequest(id) {
      const { data } = await api.post(`/daily-requests/${id}/pay`)
      return data.data
    },
    
    async cancelRequest(id) {
      const { data } = await api.post(`/daily-requests/${id}/cancel`)
      return data.data
    }
  }
})
```

## 🔐 Controle de Permissões

### utils/permissions.js
```javascript
import { useAuthStore } from '@/stores/auth'

export function can(permission) {
  const authStore = useAuthStore()
  return authStore.user?.permissions?.includes(permission) || false
}

export function hasRole(role) {
  const authStore = useAuthStore()
  return authStore.user?.roles?.includes(role) || false
}

// Uso nos componentes
<template>
  <button v-if="can('daily-requests.validate')" @click="validate">
    Validar
  </button>
</template>
```

## 🎨 Cores dos Status (Tailwind)

```javascript
const statusColors = {
  draft: 'bg-gray-100 text-gray-800',
  requested: 'bg-blue-100 text-blue-800',
  validated: 'bg-yellow-100 text-yellow-800',
  authorized: 'bg-green-100 text-green-800',
  paid: 'bg-purple-100 text-purple-800',
  cancelled: 'bg-red-100 text-red-800'
}
```

## 📱 Rotas (Vue Router)

```javascript
const routes = [
  { path: '/login', component: Login },
  {
    path: '/',
    component: DefaultLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: Dashboard },
      { path: 'daily-requests', name: 'daily-requests.index', component: DailyRequestsIndex },
      { path: 'daily-requests/create', name: 'daily-requests.create', component: DailyRequestsForm },
      { path: 'daily-requests/:id', name: 'daily-requests.show', component: DailyRequestsDetails },
      { path: 'daily-requests/:id/edit', name: 'daily-requests.edit', component: DailyRequestsForm },
      { path: 'servants', name: 'servants.index', component: ServantsIndex },
      { path: 'servants/create', name: 'servants.create', component: ServantsForm },
      { path: 'servants/:id/edit', name: 'servants.edit', component: ServantsForm },
      { path: 'legislations', name: 'legislations.index', component: LegislationsIndex },
      { path: 'departments', name: 'departments.index', component: DepartmentsIndex },
      { path: 'users', name: 'users.index', component: UsersIndex },
      { path: 'settings', name: 'settings', component: Settings }
    ]
  }
]
```

## 🚀 Próximos Passos

1. Limpar componentes do PDV antigo em `frontend/src/components`
2. Limpar views antigas em `frontend/src/views`
3. Limpar stores antigas em `frontend/src/stores`
4. Criar os novos componentes listados acima
5. Atualizar o router com as novas rotas
6. Criar os services para comunicação com a API
7. Implementar os stores do Pinia
8. Atualizar o menu lateral (Sidebar.vue)

## 📝 Observações

- O frontend atual está separado em `/frontend`, mas pode ser integrado ao Laravel usando Inertia.js no futuro
- Manter a estrutura de API REST por enquanto
- Usar Axios para comunicação com backend
- Implementar loading states e tratamento de erros
- Adicionar validações nos formulários
- Implementar feedback visual (toast/notifications) para ações
