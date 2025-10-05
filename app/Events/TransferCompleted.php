<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Transaction $transaction) {}

    /**
     * Broadcast on private channels for both sender & receiver.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->transaction->sender_id),
            new PrivateChannel('user.'.$this->transaction->receiver_id),
        ];
    }

    /**
     * Broadcast name as per frontend listener expectation.
     */
    public function broadcastAs(): string
    {
        return 'TransferCompleted';
    }

    /**
     * Custom payload with transaction and updated balances.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = User::find($this->transaction->sender_id);
        $receiver = User::find($this->transaction->receiver_id);

        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'uuid' => $this->transaction->uuid,
                'sender_id' => $this->transaction->sender_id,
                'receiver_id' => $this->transaction->receiver_id,
                'amount' => $this->transaction->amount,
                'commission_fee' => $this->transaction->commission_fee,
                'status' => $this->transaction->status,
                'created_at' => $this->transaction->created_at?->toJSON(),
            ],
            'sender_balance' => $sender?->balance,
            'receiver_balance' => $receiver?->balance,
        ];
    }
}
