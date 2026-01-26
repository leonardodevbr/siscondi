# Gestão Automática de Estoque - Adonai PDV

## Arquitetura Implementada

### Problema Identificado
O sistema tinha **duas tabelas de movimentação**:
- `inventory_movements` (antiga) - Com Observer configurado mas não utilizada
- `stock_movements` (nova) - Utilizada no código mas sem Observer

O Observer nunca disparava porque observava a tabela errada.

### Solução Implementada

#### 1. Observer Pattern
Criado `StockMovementObserver` que dispara automaticamente quando um `StockMovement` é criado.

**Arquivo:** `app/Observers/StockMovementObserver.php`

```php
public function created(StockMovement $stockMovement): void
{
    $this->processStockMovementAction->execute($stockMovement);
}
```

#### 2. Action Pattern
Criado `ProcessStockMovementAction` que centraliza a lógica de atualização de estoque.

**Arquivo:** `app/Actions/Stock/ProcessStockMovementAction.php`

**Regras de Negócio:**
- `ENTRY` (Entrada) → **Adiciona** ao estoque
- `SALE` (Venda) → **Subtrai** do estoque
- `RETURN` (Devolução) → **Adiciona** ao estoque
- `LOSS` (Perda) → **Subtrai** do estoque
- `ADJUSTMENT` (Ajuste) → Pode adicionar ou subtrair

#### 3. Registro do Observer
Registrado no `AppServiceProvider`:

```php
StockMovement::observe(StockMovementObserver::class);
```

### Fluxo de Venda Completo

#### Venda sem PIX (Finalização Imediata)
1. Controller/Action cria a venda com status `COMPLETED`
2. Para cada item, cria um `StockMovement` com tipo `SALE`
3. **Observer dispara automaticamente**
4. Action calcula a mudança de quantidade (negativa para venda)
5. Atualiza o `Inventory` da filial correspondente
6. Log registrado para auditoria

#### Venda com PIX (Pagamento Pendente)
1. Controller/Action cria a venda com status `PENDING_PAYMENT`
2. **Estoque NÃO é baixado ainda** (sem StockMovement)
3. Webhook do PIX confirma pagamento
4. Status da venda muda para `COMPLETED`
5. Cria `StockMovement` para cada item
6. **Observer dispara e baixa o estoque**

### Vantagens da Arquitetura

✅ **Separação de Responsabilidades:** Controllers/Actions apenas criam StockMovement  
✅ **Consistência:** Toda baixa de estoque passa pelo mesmo fluxo  
✅ **Auditoria:** Logs automáticos de todas as movimentações  
✅ **Testabilidade:** Action isolada e facilmente testável  
✅ **Manutenibilidade:** Lógica centralizada em um único lugar  
✅ **Thread-Safe:** Usa `increment/decrement` do Eloquent  

### Multi-Tenancy (Filiais)

O estoque é **isolado por `branch_id`**:
- Matriz (ID 1) pode ter 100 unidades
- Filial 2 pode ter 0 unidades
- Cada venda baixa do estoque da filial correspondente

### Código Removido

Removida a lógica manual de baixa de estoque que estava duplicada em:
- `CreateSaleAction::decrementStock()`
- `PosController::complete()`
- `PixController::webhook()`

**Antes:**
```php
$inventory->decrement('quantity', $quantity);
StockMovement::create([...]);
```

**Depois:**
```php
// Apenas cria o movimento - Observer faz o resto
StockMovement::create([...]);
```

### Testes Recomendados

1. **Venda Simples (Dinheiro)**
   - Criar venda com pagamento em dinheiro
   - Verificar se estoque foi baixado
   - Verificar se `stock_movements` foi criado

2. **Venda com PIX**
   - Criar venda com PIX (status `PENDING_PAYMENT`)
   - Verificar que estoque NÃO foi baixado
   - Simular webhook de confirmação
   - Verificar se estoque foi baixado após confirmação

3. **Multi-Filial**
   - Criar venda na Filial 1
   - Verificar que apenas o estoque da Filial 1 foi afetado
   - Filial 2 deve permanecer inalterada

4. **Cancelamento**
   - Implementar lógica de estorno (criar `StockMovement` tipo `RETURN`)
   - Observer irá adicionar o estoque de volta

### Próximos Passos

- [ ] Implementar estorno de venda (cancelamento)
- [ ] Criar testes automatizados (Feature Tests)
- [ ] Adicionar validação de estoque negativo (opcional)
- [ ] Dashboard de movimentações de estoque
- [ ] Relatório de auditoria de estoque

### Logs e Debug

Para verificar se o Observer está funcionando:

```bash
tail -f storage/logs/laravel.log | grep "Estoque atualizado"
```

Exemplo de log:
```
[2026-01-25 15:30:45] local.INFO: Estoque atualizado automaticamente
{
  "stock_movement_id": 123,
  "branch_id": 1,
  "product_variant_id": 45,
  "type": "sale",
  "quantity_change": -2,
  "new_quantity": 98
}
```

---

**Desenvolvido por:** Leonardo Oliveira  
**Data:** 25/01/2026  
**Sistema:** Adonai PDV - Laravel 11 + Vue 3
