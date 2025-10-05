<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $transactions = Transaction::query()
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Transaction $t) use ($userId) {
                return [
                    'id' => $t->id,
                    'uuid' => $t->uuid,
                    'sender_id' => $t->sender_id,
                    'receiver_id' => $t->receiver_id,
                    'amount' => $t->amount,
                    'commission_fee' => $t->commission_fee,
                    'status' => $t->status,
                    'direction' => $t->sender_id === $userId ? 'out' : 'in',
                    'created_at' => $t->created_at?->toJSON(),
                ];
            });

        return response()->json([
            'data' => [
                'balance' => $user->balance,
                'transactions' => $transactions,
            ],
        ]);
    }
}
