<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::post('/register', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
    ]);

    $user = DB::transaction(function () use ($data) {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->account()->create([
            'balance_cents' => 0,
        ]);

        return $user;
    });

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Usuário cadastrado com sucesso!',
        'user' => $user,
        'token' => $token,
    ], 201);
});

Route::post('/login', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user || !Hash::check($data['password'], $user->password)) {
        return response()->json([
            'message' => 'E-mail ou senha inválidos.',
        ], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login realizado com sucesso!',
        'user' => $user,
        'token' => $token,
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/transactions/credit', function (Request $request) {
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
     $account = $user->account;

     if ($account === null) {
        return response()->json([
            'message' => 'Conta não encontrada.'
        ], 404);
    }
    $amountCents = (int) round($data['amount'] * 100);
    $requestHash = hash('sha256', 'credit:' . $amountCents);
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

})->middleware('auth:sanctum');


Route::get('/account', function (Request $request) {
    $account = $request->user()->account;

    if ($account === null) {
        return response()->json([
            'message' => 'Conta não encontrada.',
        ], 404);
    }

    return response()->json([
        'balance_cents' => $account->balance_cents,
        'balance' => number_format($account->balance_cents / 100, 2, ',', '.'),
    ]);
})->middleware('auth:sanctum');
  

Route::post('/transactions/debit', function (Request $request) {
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
   $requestHash = hash('sha256', 'debit:' . $amountCents);
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
})->middleware('auth:sanctum');


Route::get('/transactions', function (Request $request) {
    $user = $request->user();

    $query = $user->account->transactions();

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
})->middleware('auth:sanctum');


Route::post('/transactions/{id}/reverse', function (Request $request, string $id) {
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
})->middleware('auth:sanctum');


