<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Services\ChatService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function searchUsers(Request $request)
    {
        $query = $request->query('q');
        $users = User::where('name', 'like', "%{$query}%")
            ->where('id', '!=', Auth::id())
            ->get(['id', 'name']);

        return response()->json($users);
    }

    public function getMessages($userId, ChatService $chatService)
    {
        $lastId = request('last', 0);
        $messages = $chatService->getMessages(Auth::id(), $userId, $lastId);

        return response()->json($messages);
    }

    public function store(SendMessageRequest $request, ChatService $chatService)
    {
        $from = Auth::id();
        $to = $request->to_user_id;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            if (str_starts_with($mime, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($mime, 'video/')) {
                $type = 'video';
            } else {
                return response()->json(['error' => 'Invalid file type'], 422);
            }
            $content = $file->store('chat_media', 'public');
        } else {
            $type = 'text';
            $content = $request->content;
        }

        $message = $chatService->sendMessage($from, $to, $content, $type);

        return response()->json($message);
    }
}
