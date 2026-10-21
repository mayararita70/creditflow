# CreditFlow

API REST para gerenciamento de contas e transações financeiras, desenvolvida com PHP, Laravel e PostgreSQL.

## Funcionalidades

- Cadastro e autenticação de usuários.
- Consulta de saldo da conta.
- Operações de crédito e débito.
- Proteção contra saldo insuficiente.
- Idempotência para evitar créditos duplicados.
- Consulta de extrato com filtros e paginação.
- Estorno de transações de débito.
- Testes automatizados das principais funcionalidades.

## Tecnologias

- PHP
- Laravel
- PostgreSQL
- Laravel Sanctum
- PHPUnit

## Requisitos

- PHP com as extensões exigidas pelo Laravel e pelo driver PostgreSQL.
- Composer.
- PostgreSQL.
- Git.

## Instalação

## Como obter o projeto

```bash
Acesse o repositório do CreditFlow no GitHub e utilize a opção **Code → Download ZIP**, ou clone o repositório usando a URL disponibilizada no GitHub.
```

Instale as dependências:

```bash
composer install
```

Copie o arquivo de ambiente de exemplo:

```bash
copy .env.example .env
```

Gere a chave da aplicação:

```bash
php artisan key:generate
```

Configure no arquivo `.env` as credenciais do PostgreSQL e o nome do banco de dados utilizado pela aplicação.

Execute as migrations:

```bash
php artisan migrate
```

## Executar a aplicação

Inicie o servidor de desenvolvimento:

```bash
php artisan serve
```

Por padrão, a aplicação fica disponível em `http://127.0.0.1:8000`.

## Testes automatizados

Execute a suíte de testes:

```bash
php artisan test
```

Configure o ambiente de testes para utilizar um banco separado do banco principal antes de executar os testes.

## Autenticação

As rotas protegidas exigem autenticação por meio do Laravel Sanctum. Consulte as rotas definidas em `routes/api.php` para conhecer os endpoints disponíveis e os métodos HTTP utilizados.

As operações de crédito que exigem idempotência devem receber a chave `Idempotency-Key` no cabeçalho da requisição.

## Observações

- Os valores monetários são armazenados em centavos inteiros.
- As operações financeiras devem preservar a consistência do saldo e do histórico de transações.
- Não envie arquivos `.env` ou credenciais para o repositório.

## Licença

Projeto desenvolvido para fins de estudo e demonstração técnica.
