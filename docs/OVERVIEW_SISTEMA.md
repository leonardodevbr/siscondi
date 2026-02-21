# SISCONDI – Overview do Sistema

Visão geral do **Sistema de Concessão de Diárias** (SISCONDI): módulos, fluxos, tecnologias e melhorias recentes.

---

## 1. Objetivo do sistema

O SISCONDI é um sistema governamental para **gestão de solicitações de diárias** de servidores públicos municipais: desde a solicitação até o pagamento, passando por validação e autorização, com controle por secretaria e por perfil de acesso.

---

## 2. Stack técnico

| Camada      | Tecnologia |
|------------|------------|
| Backend    | Laravel 10+, PHP 8.1+ |
| API        | REST, Laravel Sanctum (token) |
| Banco      | MySQL/PostgreSQL (migrations) |
| ACL        | Spatie Laravel Permission (roles + permissions) |
| Frontend   | Vue 3, Vue Router, Pinia, Vite |
| UI         | Tailwind CSS |
| Planilhas  | Maatwebsite/Excel (import/export) |
| PDF        | DomPDF (solicitações, relatórios) |
| Realtime   | Laravel Echo (WebSockets) para progresso de importação e notificações |
| Push       | Web Push (config webpush) |

---

## 3. Estrutura de dados (principais entidades)

- **municipalities** – Municípios (multi-município para super-admin).
- **departments** – Secretarias/setores (lotação; no doc de escopo chamado “Branch”).
- **users** – Usuários do sistema: `name`, `email`, `username`, `matricula`, `password`, `municipality_id`, `primary_department_id`, `position_id`, assinatura, PIN/senha de operação.
- **servants** – Servidores públicos: dados pessoais (nome, CPF, RG, matrícula), bancários, cargo (`position_id`), lotação (`department_id`), vínculo opcional com `users` (`user_id`).
- **positions** – Cargos/posições (símbolo, nome, role padrão).
- **legislations** – Legislações (lei, vigência).
- **legislation_items** – Itens da lei: cargo (position) + valores por tipo de destino (ex.: até 200 km, capital, exterior).
- **legislation_item_position** – Pivot legislação ↔ posição (código do cargo na lei).
- **daily_requests** – Solicitações de diárias: servidor, tipo de destino, cidade/UF, datas, quantidade, valor unitário/total, status, requester/validator/authorizer/payer, datas de validação/autorização/pagamento.
- **daily_request_logs** – Auditoria (timeline) das ações nas solicitações.
- **settings** – Configurações globais (chave/valor/grupo/tipo), ex.: `app_name`, `allowed_login_methods`.

---

## 4. Fluxo de uma solicitação de diária

1. **Rascunho** – Requerente cria e pode editar.
2. **Solicitado** – Envio para validação.
3. **Validado** – Validador (ex.: secretário) valida.
4. **Concedido** – Concedente autoriza.
5. **Pago** – Pagador registra o pagamento.
6. **Cancelado** – Pode ser cancelado/indeferido em etapas permitidas.

Transições envolvem assinatura (e opcionalmente PIN/senha de operação), registro em `daily_request_logs` e atualização de `validator_id`/`authorizer_id`/`payer_id` e respectivas datas.

---

## 5. Perfis e permissões

- **super-admin** – Acesso total; municípios; configurações; não usa “secretaria primária”.
- **admin** – Acesso ao município; dados do município; usuários/secretarias do município.
- **requester** – Ver/criar/editar solicitações (próprias).
- **validator** – Validar solicitações da sua secretaria; ver servidores.
- **authorizer** – Conceder solicitações validadas; legislações; relatórios.
- **payer** – Registrar pagamento; relatórios.
- **beneficiary** – Servidor com acesso (vinculado a um `servant`); ver/criar solicitações como beneficiário.

Permissões granulares: `users.*`, `departments.*`, `legislations.*`, `positions.*`, `servants.*`, `daily-requests.*` (view/create/edit/delete/validate/authorize/pay/cancel), `reports.view`/`export`, `settings.manage`/`system`. Rotas e menu são filtrados por permissão ou role.

---

## 6. Módulos da aplicação (frontend)

| Módulo            | Rotas / Views                    | Descrição |
|-------------------|----------------------------------|-----------|
| Auth              | Login, Esqueci senha, Reset, Escolher secretaria | Login por e-mail, usuário ou matrícula (conforme settings); primeiro acesso com escolha de secretaria quando > 1. |
| Dashboard         | `/`                              | Resumo por status, resumo financeiro, solicitações recentes; botão “Nova Solicitação”. |
| Solicitações      | `/daily-requests` (list, create, edit, show) | CRUD + fluxo de validação/concessão/pagamento/cancelamento; formulário compacto em 2 colunas. |
| Servidores        | `/servants` (list, create, edit) | Cadastro manual + **importação em massa** (planilha com template; geração de username primeiro.ultimo quando vazio; criação/vinculação de User com e-mail). |
| Cargos            | `/positions`                     | CRUD de cargos/posições (símbolo, nome, role). |
| Legislações       | `/legislations`                  | CRUD de legislações e itens (valores por tipo de destino). |
| Secretarias       | `/departments`                   | CRUD de secretarias (por município). |
| Usuários          | `/users`, `/users/new`, `/users/:id/edit` | CRUD com nome, e-mail, **username**, **matricula**, perfis, secretarias, servidor vinculado, assinatura, PIN/senha de operação. |
| Relatórios        | `/reports`                      | Relatórios de solicitações e de servidores (export CSV/PDF). |
| Perfil            | `/profile`                      | Dados do usuário logado. |
| Dados do município| `/municipality`                 | Admin: dados do município. |
| Municípios        | `/municipalities`               | Super-admin: listar/editar municípios. |
| Configurações     | `/settings`                     | Super-admin: app_name, **allowed_login_methods** (E-mail, Usuário, Matrícula). |

---

## 7. Funcionalidades implementadas (destaques)

- **Login flexível**: e-mail, **username** ou **matrícula**; configuração **allowed_login_methods** em Settings (quais métodos aceitos).
- **Usuário com username/matrícula**: campos no formulário de usuário (criação/edição), validação e persistência; resource expõe os campos.
- **Importação de servidores**: template com coluna **Username**; se vazio, gera `primeiro.ultimo` (minúsculo, sem acentos); garante unicidade no User ao criar/vincular.
- **Dashboard**: sem cards de totais (servidores/legislações/solicitações); mantém blocos por status, resumo financeiro e recentes.
- **Formulário de solicitação**: layout compacto, 2 colunas, agrupado (servidor/destino, local/período, quantidade/valor, finalidade/motivo).
- **Fluxo de assinaturas**: validação, concessão e pagamento com confirmação (e opcionalmente PIN/senha de operação); timeline de auditoria.
- **Multi-município e multi-secretaria**: usuários com `primary_department_id` e departamentos; tela “Escolher secretaria” quando aplicável.
- **Notificações**: Web Push; Echo para progresso da importação de servidores.

---

## 8. APIs principais (resumo)

- **Auth**: `POST /api/login` (login, password), `POST /api/logout`, `GET /api/user`.
- **Config**: `GET /api/config` (app name, departamentos do usuário, etc.).
- **Settings**: `GET/PUT` (apenas super-admin).
- **Municipalities, Departments, Legislations, Positions, Servants, Daily-requests**: CRUD + endpoints específicos (validate, authorize, pay, cancel, timeline).
- **Servants import**: template, validate, import, status (progresso).
- **Dashboard**: `GET /api/dashboard`.
- **Reports**: listagem e export (CSV/PDF).
- **Upload**: assinaturas, etc.
- **Push**: subscribe, test.

---

## 9. Ajustes sugeridos (pequenos)

- **README**: Atualizar para refletir login por usuário/matrícula, Settings (allowed_login_methods), remoção dos cards do dashboard, e referência a “Secretarias” em vez de “Branches” onde fizer sentido.
- **Consistência de nomenclatura**: Garantir que em toda a UI se use “Secretaria(s)” e não “Branch/Filial” (já predominante no Sidebar e rotas).
- **Formulário de solicitação**: Revisar placeholders e textos de ajuda nos campos que foram encurtados para manter clareza.
- **Importação de servidores**: Incluir no README ou em documentação interna a descrição da coluna Username (opcional; geração automática primeiro.ultimo).
- **Testes**: Garantir que Settings e Auth (login com username/matricula e restrição por allowed_login_methods) tenham testes automatizados quando houver suite de testes ativa.

---

## 10. Portal da Transparência (público)

- **Objetivo**: Página pública de consulta a diárias e passagens, acessível pelo site da prefeitura. **URL**: `/transparencia` (sem autenticação). **Layout**: Cabeçalho com brasão do município, título "Portal da Transparência - diárias e passagens" e nome do município. **Filtros**: Exercício, Mês inicial/final, Gestão, Destino, Servidor. **Ações**: Pesquisar, Limpar, Imprimir, PDF, Exportar CSV. **API**: `GET /api/public/transparency/config`, `GET /api/public/transparency/daily-allowances` (apenas solicitações pagas).

- **Módulo de Tipos de Destino (ou “Faixas de Destino”)**  
  - Hoje os tipos de destino vêm da API (ex.: `legislations/destination-types`) e são usados na solicitação e nos itens da legislação.  
  - Um módulo de **cadastro de tipos de destino** permitiria:  
    - Definir nomes padronizados (ex.: “Até 200 km”, “Capital do Estado”, “Exterior”).  
    - Ordenação e ativo/inativo.  
    - Evitar strings soltas e facilitar relatórios e filtros.  
  - Envolveria: migration (ex.: `destination_types`), model, controller, rotas, permissões (ex.: `destination-types.view/create/edit/delete`), tela de listagem/cadastro no menu “Cadastros” e uso no formulário de solicitação e na legislação.

Alternativa: **Módulo de Notificações/Comunicações** (histórico de e-mails enviados pelo sistema, ex.: primeiro acesso, reset de senha) para auditoria e suporte.

---

## 11. Referências no repositório

- Escopo e decisões: `docs/ANALISE_ESCOPO_E_BRANCH.md`
- Seeders: `README_SEEDERS.md`
- Migrations: `database/migrations/`
- Rotas API: `routes/api/*.php`
- Rotas frontend: `resources/js/router/index.js`
- Menu: `resources/js/components/Layout/Sidebar.vue`

---

*Documento gerado para apoio a revisões e evolução do SISCONDI.*
