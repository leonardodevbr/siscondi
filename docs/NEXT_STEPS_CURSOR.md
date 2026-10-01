# Próximos passos para o Cursor

O scaffold já define a arquitetura e as telas-base. Antes de gerar código novo:

1. Rode migrations, seed e build.
2. Corrija qualquer incompatibilidade de dependência sem trocar a stack.
3. Preserve Laravel + Vue SPA + Sanctum + Spatie.
4. Não remova o fluxo offline nem a idempotência de sincronização.
5. Mantenha `municipality_id` nos dados de domínio.
6. Implemente por módulo, com commits pequenos.

## Ordem sugerida

### Etapa A — estabilizar
- login/logout/me
- layout responsivo
- seed local
- dashboard
- testes de API

### Etapa B — mapeamento
- CRUD completo de agentes
- comunidades/localidades
- manifestações e vínculos
- mapa com clusters
- filtros

### Etapa C — busca ativa
- formulário mobile
- captura de geolocalização
- rascunho offline
- fila IndexedDB
- sincronização
- tela de conflitos e possíveis duplicidades

### Etapa D — carteiras
- emissão
- numeração municipal
- QR Code
- página pública de validação
- PDF/arte para impressão física

### Etapa E — PNAB
- editais configuráveis
- inscrições
- documentos
- comissão
- notas
- classificação e recursos
- execução
- prestação de contas

### Etapa F — projetos
- orçamento
- fornecedores
- despesas
- documentos
- pendências
- dossiê final

Não invente regras de PNAB. Deixe critérios e documentos configuráveis por edital.
