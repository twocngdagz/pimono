<?php

namespace App\Http\Controllers;

use App\Events\TransferCompleted;
use App\Http\Requests\TransferRequest;
use App\Services\Wallet\TransferService;
use Illuminate\Http\JsonResponse;

use function broadcast;

class TransferController extends Controller
{
    public function store(TransferRequest $request, TransferService $service): JsonResponse
    {
        $sender = $request->user();
        $receiverId = (int) $request->input('receiver_id');
        $amount = (string) $request->input('amount');
        $idempotencyKey = $request->input('idempotency_key');

        $tx = $service->transfer($sender, $receiverId, $amount, $idempotencyKey);

        // Broadcast to sender & receiver private channels (excluding origin socket)
        broadcast(new TransferCompleted($tx))->toOthers();

        return response()->json([
            'data' => [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'sender_id' => $tx->sender_id,
                'receiver_id' => $tx->receiver_id,
                'amount' => $tx->amount,
                'commission_fee' => $tx->commission_fee,
                'status' => $tx->status,
                'created_at' => $tx->created_at?->toJSON(),
            ],
        ], 201);
    }
}
