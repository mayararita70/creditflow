# Decisões Técnicas — CreditFlow

## 1. Valores em centavos inteiros

**Decisão:** saldo e valores das movimentações são guardados como inteiros em centavos (`balance_cents` e `amount_cents`, tipo `bigint`). A API recebe o valor em reais com até 2 casas decimais (por exemplo, `50.00`), valida com `decimal:0,2` e `gt:0`, e converte para centavos.

**Motivo:** números de ponto flutuante não representam decimais com exatidão (`0.1 + 0.2` resulta em `0.30000000000000004`). Com inteiros, somar e subtrair é exato. A conversão usa `round($valor * 100)`, que é segura porque a validação limita o valor a 2 casas decimais.

## 2. Transação de banco com trava na conta

**Decisão:** crédito, débito e estorno rodam dentro de `DB::transaction()`, e a primeira coisa feita é buscar a conta com `lockForUpdate()`. O saldo só é lido depois da trava.

**Motivo:** a transação garante que saldo e histórico mudam juntos ou não mudam (atomicidade). A trava garante que, se duas requisições chegarem ao mesmo tempo para a mesma conta, a segunda espera a primeira terminar e já enxerga o saldo atualizado. Sem isso, as duas poderiam ler o mesmo saldo e gastá-lo duas vezes.

## 3. Idempotência por conta, com hash que inclui o tipo

**Decisão:** crédito e débito exigem o cabeçalho `Idempotency-Key`. Guardamos a chave e um hash do conteúdo (`credit:<centavos>` ou `debit:<centavos>`). Há índice único em (`account_id`, `idempotency_key`). Ao receber uma chave já usada:
- mesmo tipo e mesmo valor: não executa de novo e responde sucesso;
- tipo ou valor diferente: rejeita com 409.

**Motivo:** o cliente pode repetir um pedido por falha de rede, e isso não pode cobrar duas vezes. O tipo entra no hash porque, numa revisão, descobrimos que um débito e um crédito de mesmo valor tinham o mesmo hash, e o crédito era tratado como repetição sem ser executado. O teste `reusing a debit key for credit is rejected` cobre esse caso. A unicidade é por conta, então usuários diferentes podem usar a mesma chave sem conflito.

## 4. Estorno como movimentação compensatória

**Decisão:** estornar cria uma nova linha do tipo `reversal`, ligada à original por `reversal_of_id`. A original nunca é alterada nem apagada. Há índice único em `reversal_of_id`, e o código recusa estornar um estorno. Estornar um crédito só é permitido se o saldo atual cobrir o valor.

**Motivo:** o histórico financeiro precisa ser auditável, então não se apaga nem se edita um lançamento. O índice único faz o próprio banco impedir um segundo estorno do mesmo lançamento, mesmo que duas requisições cheguem juntas. A checagem de saldo no estorno de crédito mantém a regra de que o saldo nunca fica negativo.

## 5. Regras críticas também no banco de dados

**Decisão:** além do código, o banco impõe regras com `CHECK` (`balance_cents >= 0`, `amount_cents > 0`, tipo entre `credit`, `debit` e `reversal`), índices únicos (idempotência, estorno e uma conta por usuário) e chaves estrangeiras.

**Motivo:** o código PHP pode ter um bug ou ser burlado por uma situação que não previmos. O banco é a última barreira e protege os dados mesmo assim. Por isso, as regras mais importantes existem nas duas camadas.

## Isolamento entre usuários

Toda consulta parte da conta do usuário autenticado (`$user->account`). Por isso, um `id` de outra pessoa na URL do estorno resulta em 404. O "responsável" de cada movimentação é o dono da conta, já que só o dono opera a própria conta.

## Limitações conhecidas

## Limitações conhecidas

- Ao repetir uma requisição idempotente, a API devolve o saldo atual, e não uma cópia exata da resposta original.
- A atomicidade tem teste automatizado (falha simulada ao gravar o histórico). A concorrência é garantida por `lockForUpdate()` e explicada neste documento, mas não há teste com requisições simultâneas reais.
- A lógica de crédito, débito e estorno está nos métodos do `TransactionController`, e as rotas em `routes/api.php` só apontam para eles. Crédito e débito ainda repetem parte do código. Uma evolução natural é extrair essa parte para uma classe de serviço e mover a validação para Form Requests.
