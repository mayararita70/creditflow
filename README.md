# CreditFlow

API REST para gestão de créditos de usuários, feita com PHP 8.3, Laravel e PostgreSQL. Cada usuário tem uma conta com saldo, pode creditar, debitar, consultar o extrato e estornar movimentações.

## Tecnologias

- PHP 8.3
- Laravel
- PostgreSQL
- Laravel Sanctum (autenticação por token)
- PHPUnit

## Instalação

1. Clone o repositório e entre na pasta:

```bash
git clone https://github.com/mayararita70/creditflow.git
cd creditflow
```

2. Instale as dependências:

```bash
composer install
```

3. Crie o arquivo de ambiente e gere a chave da aplicação:

```bash
copy .env.example .env
php artisan key:generate
```

4. Crie dois bancos no PostgreSQL: um para a aplicação e outro para os testes:

```sql
CREATE DATABASE creditflow;
CREATE DATABASE creditflow_test;
```

5. No arquivo `.env`, configure a conexão (nunca envie o `.env` ao Git):

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=creditflow
DB_USERNAME=postgres
DB_PASSWORD=sua_senha
```

6. Crie as tabelas:

```bash
php artisan migrate
```

7. Inicie o servidor:

```bash
php artisan serve
```

A API fica em `http://127.0.0.1:8000/api`.

## Testes

Os testes usam o banco separado `creditflow_test` (configurado no `phpunit.xml`), para nunca apagar os dados do banco principal.

```bash
php artisan test
```

## Endpoints

| Método | Rota | Finalidade | Autenticação |
|---|---|---|---|
| POST | `/api/register` | Criar usuário e conta | Não |
| POST | `/api/login` | Autenticar usuário | Não |
| GET | `/api/account` | Consultar saldo | Sim |
| POST | `/api/transactions/credit` | Registrar crédito | Sim |
| POST | `/api/transactions/debit` | Registrar débito | Sim |
| POST | `/api/transactions/{id}/reverse` | Estornar movimentação | Sim |
| GET | `/api/transactions` | Extrato paginado | Sim |

Rotas autenticadas exigem o cabeçalho `Authorization: Bearer SEU_TOKEN` (o token vem no cadastro e no login). Também existe `GET /api/user`, que devolve o usuário autenticado.

Crédito e débito exigem o cabeçalho `Idempotency-Key`.

## Exemplos de requisição

**Cadastro**

```
POST /api/register
Content-Type: application/json

{ "name": "Maria", "email": "maria@exemplo.com", "password": "senha12345" }
```

Resposta (201): `{ "message": "...", "user": {...}, "token": "..." }`

**Login**

```
POST /api/login
Content-Type: application/json

{ "email": "maria@exemplo.com", "password": "senha12345" }
```

**Crédito**

```
POST /api/transactions/credit
Authorization: Bearer SEU_TOKEN
Idempotency-Key: deposito-001
Content-Type: application/json

{ "amount": 50.00 }
```

Resposta (200): `{ "message": "...", "balance_cents": 5000 }`

**Débito**

```
POST /api/transactions/debit
Authorization: Bearer SEU_TOKEN
Idempotency-Key: saque-001
Content-Type: application/json

{ "amount": 20.00 }
```

**Saldo**

```
GET /api/account
Authorization: Bearer SEU_TOKEN
```

Resposta: `{ "balance_cents": 3000, "balance": "30,00" }`

**Extrato com filtros**

```
GET /api/transactions?type=credit&start_date=2026-10-01&end_date=2026-10-31
Authorization: Bearer SEU_TOKEN
```

Filtros opcionais: `type` (`credit`, `debit` ou `reversal`), `start_date` e `end_date`. Paginação de 10 itens por página (`?page=2`).

**Estorno**

```
POST /api/transactions/15/reverse
Authorization: Bearer SEU_TOKEN
```

## Códigos de resposta

| Código | Quando acontece |
|---|---|
| 401 | Sem token ou token inválido |
| 404 | Conta ou movimentação não encontrada (inclui movimentação de outro usuário) |
| 409 | Chave de idempotência já usada em outra operação, ou movimentação já estornada |
| 422 | Valor inválido, chave ausente, saldo insuficiente ou estorno não permitido |

## Regras de negócio

- **Valores em centavos inteiros.** A API recebe o valor em reais com até 2 casas decimais (`50.00`) e guarda em centavos (`5000`). Assim não há erro de ponto flutuante.
- **Saldo nunca fica negativo.** A checagem é feita no código e há uma restrição `CHECK` no banco.
- **Idempotência.** Repetir a mesma chave com o mesmo conteúdo não gera novo efeito. A mesma chave com conteúdo ou tipo diferente é rejeitada (409).
- **Estorno compensatório.** O estorno cria uma nova movimentação do tipo `reversal`, sem alterar a original. Um lançamento só pode ser estornado uma vez (índice único no banco), e um estorno não pode ser estornado.
- **Atomicidade e concorrência.** Saldo e histórico são alterados na mesma transação de banco, com a conta travada (`lockForUpdate`) para impedir que duas requisições gastem o mesmo saldo.
- **Isolamento.** Todas as consultas partem da conta do usuário autenticado.

## Uso de inteligência artificial

Foi usado o Claude (Anthropic) como apoio para montar o ambiente, discutir a modelagem e revisar o código.

Exemplo de código validado e corrigido: na revisão, foi identificado que o crédito conferia só o hash do valor ao receber uma chave de idempotência repetida. Assim, um débito e um crédito de mesmo valor com a mesma chave eram tratados como repetição, e o crédito deixava de ser feito sem aviso. A correção incluiu o tipo da operação no hash (`credit:` e `debit:`) e a conferência do tipo no crédito. O teste `reusing a debit key for credit is rejected` cobre esse caso.