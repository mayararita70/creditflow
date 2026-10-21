# Decisões Técnicas — CreditFlow

## 1. Linguagem e framework

O projeto foi desenvolvido em PHP utilizando o framework Laravel. A escolha permite organizar as rotas, validações, autenticação, acesso ao banco de dados e testes automatizados de forma estruturada.

## 2. Banco de dados

Foi utilizado PostgreSQL para armazenar usuários, contas e transações financeiras.

O saldo e os valores das transações são armazenados em centavos, utilizando números inteiros (`amount_cents` e `balance_cents`). Essa abordagem evita os problemas de precisão que podem ocorrer ao utilizar números de ponto flutuante para representar valores monetários.

## 3. Autenticação

A API utiliza Laravel Sanctum para proteger as rotas que exigem autenticação. Dessa forma, as operações financeiras e a consulta de informações da conta dependem de um usuário autenticado.

## 4. Idempotência nas operações financeiras

As operações de crédito utilizam uma chave de idempotência enviada no cabeçalho `Idempotency-Key`.

O objetivo é evitar que uma mesma solicitação seja processada mais de uma vez quando o cliente repete uma requisição. A chave é associada aos dados da solicitação para permitir a identificação de repetições e a rejeição de reutilizações incompatíveis.

## 5. Controle de saldo e transações

As operações financeiras utilizam transações de banco de dados para manter a consistência das alterações. O saldo é atualizado junto ao registro da movimentação, e o débito é recusado quando não há saldo suficiente.

## 6. Estorno

O estorno é registrado como uma nova transação do tipo `reversal`, relacionada à transação original por meio de `reversal_of_id`.

Essa abordagem preserva o histórico financeiro em vez de apagar a movimentação original. O sistema também impede o estorno repetido da mesma transação nos cenários testados.

## 7. Extrato

O extrato permite consultar as transações da conta autenticada, filtrar movimentações e limitar a quantidade de registros retornados por página.

## 8. Testes automatizados

Foram criados testes automatizados para cadastro, login, consulta de saldo, crédito, débito, extrato e estorno. Os testes utilizam um banco de dados separado, `creditflow_test`, para evitar que a execução da suíte altere os dados do banco principal.

