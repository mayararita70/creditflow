<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if ($account === null) {
            return response()->json([
                'message' => 'Conta não encontrada.',
            ], 404);
        }

        $query = $account->transactions();

        if ($request->filled('type')) {
            $request->validate([
                'type' => ['in:credit,debit,reversal'],
            ]);

            $query->where('type', $request->query('type'));
        }

        if ($request->filled('start_date')) {
            $request->validate([
                'start_date' => ['date'],
            ]);

            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $request->validate([
                'end_date' => ['date'],
            ]);

            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($transactions);
    }

    public function credit(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return response()->json([
                'message' => 'Uma chave de idempotência válida é obrigatória.',
            ], 422);
        }

        if ($user->account === null) {
            return response()->json([
                'message' => 'Conta não encontrada.',
            ], 404);
        }

        $amountCents = (int) round($data['amount'] * 100);
        $requestHash = hash('sha256', 'credit:'.$amountCents);

        $newBalance = DB::transaction(function () use ($user, $amountCents, $idempotencyKey, $requestHash) {
            $account = $user->account()
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                abort(404, 'Conta não encontrada.');
            }

            $existingTransaction = $account->transactions()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransaction !== null) {
                if ($existingTransaction->type !== 'credit' ||
                    $existingTransaction->request_hash !== $requestHash) {
                    abort(409, 'Esta chave já foi usada em outra operação.');
                }

                return $account->balance_cents;
            }

            $newBalance = $account->balance_cents + $amountCents;

            $account->update([
                'balance_cents' => $newBalance,
            ]);

            $account->transactions()->create([
                'type' => 'credit',
                'amount_cents' => $amountCents,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
            ]);

            return $newBalance;
        });

        return response()->json([
            'message' => 'Depósito realizado com sucesso!',
            'balance_cents' => $newBalance,
        ]);
    }

    public function debit(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return response()->json([
                'message' => 'Uma chave de idempotência válida é obrigatória.',
            ], 422);
        }

        $amountCents = (int) round($data['amount'] * 100);
        $requestHash = hash('sha256', 'debit:'.$amountCents);

        $newBalance = DB::transaction(function () use ($user, $amountCents, $idempotencyKey, $requestHash) {
            $account = $user->account()
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                abort(404, 'Conta não encontrada.');
            }

            $existingTransaction = $account->transactions()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransaction !== null) {
                if ($existingTransaction->type !== 'debit' ||
                    $existingTransaction->request_hash !== $requestHash) {
                    abort(409, 'Esta chave já foi usada em outra operação.');
                }

                return $account->balance_cents;
            }

            if ($account->balance_cents < $amountCents) {
                abort(422, 'Saldo insuficiente.');
            }

            $newBalance = $account->balance_cents - $amountCents;

            $account->update([
                'balance_cents' => $newBalance,
            ]);

            $account->transactions()->create([
                'type' => 'debit',
                'amount_cents' => $amountCents,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
            ]);

            return $newBalance;
        });

        return response()->json([
            'message' => 'Saque realizado com sucesso!',
            'balance_cents' => $newBalance,
        ]);
    }

    public function reverse(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $newBalance = DB::transaction(function () use ($user, $id) {
            $account = $user->account()
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                abort(404, 'Conta não encontrada.');
            }

            $transaction = $account->transactions()
                ->where('id', $id)
                ->first();

            if ($transaction === null) {
                abort(404, 'Transação não encontrada.');
            }

            if ($transaction->type === 'reversal') {
                abort(422, 'Não é possível estornar um estorno.');
            }

            $alreadyReversed = $account->transactions()
                ->where('reversal_of_id', $transaction->id)
                ->exists();

            if ($alreadyReversed) {
                abort(409, 'Esta transação já foi estornada.');
            }

            if ($transaction->type === 'credit') {
                if ($account->balance_cents < $transaction->amount_cents) {
                    abort(422, 'Saldo insuficiente para estornar este depósito.');
                }

                $newBalance = $account->balance_cents - $transaction->amount_cents;
            } else {
                $newBalance = $account->balance_cents + $transaction->amount_cents;
            }

            $account->update([
                'balance_cents' => $newBalance,
            ]);

            $account->transactions()->create([
                'type' => 'reversal',
                'amount_cents' => $transaction->amount_cents,
                'reversal_of_id' => $transaction->id,
            ]);

            return $newBalance;
        });

        return response()->json([
            'message' => 'Estorno realizado com sucesso!',
            'balance_cents' => $newBalance,
        ]);
    }
}