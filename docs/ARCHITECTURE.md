# Arquitetura inicial

## Princípios

1. O sistema é interno primeiro, mas os dados publicáveis devem ficar separados dos dados administrativos.
2. Município é a fronteira de isolamento dos dados.
3. Busca ativa é offline-first: o celular grava localmente e sincroniza depois.
4. Sincronização nunca deve mesclar conflitos silenciosamente.
5. A PNAB possui fluxo próprio, sem engessar regras que podem mudar por edital.
6. Carteira cultural é identificação municipal e não deve ser tratada como substituta de registro profissional externo.

## Domínios

- Identidade e acesso
- Território e comunidades
- Agentes, grupos e manifestações
- Busca ativa e sincronização
- Carteiras
- PNAB
- Projetos e execução
- Relatórios
- Futuro portal público

## Offline

O navegador mantém rascunhos e uma fila de operações no IndexedDB. Cada operação recebe um UUID do cliente. O backend registra o UUID para garantir idempotência e devolve conflitos para revisão humana.

## Multi-município

As entidades de domínio carregam `municipality_id`. Nesta implantação inicial haverá Cafarnaum, mas a estrutura não fica presa a um único município.
