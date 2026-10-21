<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
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
    }
}