<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function sendMessage($fromUserId, $toUserId, $content, $type): Message
    {
        $message = Message::create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'content' => $content,
            'type' => $type,
        ]);

        event(new MessageSent($message));

        // Notify local websocket server (admin port) so it can broadcast to clients
        // Non-blocking short TCP write; wrap in try/catch to avoid breaking the request on failure
        try {
            $payload = json_encode([
                'action' => 'new_message',
                'message' => $message->toArray(),
            ]);
            $sock = @stream_socket_client('tcp://127.0.0.1:9000', $errno, $errstr, 0.1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
            if ($sock) {
                stream_set_blocking($sock, false);
                fwrite($sock, $payload);
                // close immediately; ws_server will read and broadcast
                fclose($sock);
            }
        } catch (\Throwable $e) {
            // optionally log failure but do not fail the message store
            Log::error('Failed to notify WS server: ' . $e->getMessage());
        }

        return $message;
    }


    public function getMessages($userId1, $userId2, $afterId = 0): \Illuminate\Database\Eloquent\Collection
    {
        return Message::where(function ($query) use ($userId1, $userId2) {
            $query->where('from_user_id', $userId1)->where('to_user_id', $userId2);
        })->orWhere(function ($query) use ($userId1, $userId2) {
            $query->where('from_user_id', $userId2)->where('to_user_id', $userId1);
        })->where('id', '>', $afterId)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
