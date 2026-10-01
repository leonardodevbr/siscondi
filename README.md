# Cultura em Cafarnaum

Scaffold inicial do sistema interno da Diretoria Municipal de Cultura de Cafarnaum/BA.

Este branch foi derivado da base técnica do SISCONDI, mas contém somente a estrutura necessária para o novo domínio cultural. A proposta é servir como ponto de partida para desenvolvimento no Cursor, sem carregar o domínio de diárias.

## Stack

- Laravel 12 + PHP 8.2+
- Vue 3 SPA + Vue Router + Pinia
- Tailwind CSS
- Laravel Sanctum
- Spatie Laravel Permission
- Leaflet + MarkerCluster
- IndexedDB (idb) para fila offline
- MySQL ou PostgreSQL

## Escopo já preparado

- Dashboard
- Mapeamento cultural
- Cadastro de agentes e manifestações
- Busca ativa offline-first
- Carteiras culturais com numeração e validação
- Área PNAB
- Projetos e execução
- Relatórios
- Isolamento por município desde a modelagem

## Rodar localmente

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Usuário local gerado pelo seeder:

- e-mail: `admin@cultura.local`
- senha: `password`

Use somente em desenvolvimento.

## Clonar somente este scaffold

```bash
git clone -b scaffold/cultura-cafarnaum --single-branch https://github.com/leonardodevbr/siscondi.git cultura-cafarnaum
cd cultura-cafarnaum
```

Depois de criar o repositório definitivo, troque o remote normalmente.

## Próximo trabalho

Leia `docs/NEXT_STEPS_CURSOR.md` antes de pedir alterações ao agente no Cursor.
