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

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        $tx = $service->transfer($sender, $receiverId, $amount, $idempotencyKey);

        $replayed = $idempotencyKey && $tx->uuid === $idempotencyKey && $tx->wasRecentlyCreated === false;

        broadcast(new TransferCompleted($tx))->toOthers();

        $status = $replayed ? 200 : 201;
        $response = response()->json([
            'data' => [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'sender_id' => $tx->sender_id,
                'receiver_id' => $tx->receiver_id,
                'amount' => $tx->amount,
                'commission_fee' => $tx->commission_fee,
                'status' => $tx->status,
                'created_at' => $tx->created_at?->toJSON(),
                'idempotent_replay' => $replayed,
            ],
        ], $status);

        if ($replayed) {
            $response->headers->set('Idempotent-Replay', 'true');
        }

        return $response;
    }
}
