<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) { // hard cap
            $limit = 50;
        }
        $beforeId = $request->query('before_id');

        $cols = [
            'id', 'uuid', 'sender_id', 'receiver_id', 'amount', 'commission_fee', 'status', 'created_at',
        ];

        $outQuery = Transaction::query()
            ->select(array_merge($cols, [DB::raw("'out' as direction")]))
            ->where('sender_id', $userId);
        $inQuery = Transaction::query()
            ->select(array_merge($cols, [DB::raw("'in' as direction")]))
            ->where('receiver_id', $userId);

        if ($beforeId) {
            if (ctype_digit((string) $beforeId)) {
                $outQuery->where('id', '<', (int) $beforeId);
                $inQuery->where('id', '<', (int) $beforeId);
            }
        }

        // Union and order / limit in outer query to merge streams efficiently.
        $union = $outQuery->unionAll($inQuery);
        $rows = DB::query()
            ->fromSub($union, 't')
            ->orderByDesc('id')
            ->limit($limit + 1) // fetch one extra to compute has_more
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $transactions = $rows->map(function ($t) {
            $createdRaw = $t->created_at;
            if ($createdRaw instanceof \DateTimeInterface) {
                $created = $createdRaw->format('c');
            } elseif (is_string($createdRaw) && $createdRaw !== '') {
                try {
                    $created = \Carbon\Carbon::parse($createdRaw)->toJSON();
                } catch (\Throwable) {
                    $created = $createdRaw; // fallback raw
                }
            } else {
                $created = null;
            }

            return [
                'id' => (int) $t->id,
                'uuid' => $t->uuid,
                'sender_id' => (int) $t->sender_id,
                'receiver_id' => (int) $t->receiver_id,
                'amount' => $t->amount,
                'commission_fee' => $t->commission_fee,
                'status' => $t->status,
                'direction' => $t->direction,
                'created_at' => $created,
            ];
        })->values();

        $nextCursor = $hasMore ? $transactions->last()['id'] : null;

        return response()->json([
            'data' => [
                'balance' => $user->balance,
                'transactions' => $transactions,
            ],
            'meta' => [
                'limit' => $limit,
                'next_before_id' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
