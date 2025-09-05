<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Message;

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
