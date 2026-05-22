<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\ClientNotificationService;
use App\Support\CurrentClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNotificationApiController extends Controller
{
    public function __construct(private ClientNotificationService $notifications) {}

    public function index(): JsonResponse
    {
        $client = CurrentClient::get();
        $items = $this->notifications->listForClient($client);

        return response()->json([
            'status' => 'success',
            'unread_count' => $this->notifications->unreadCount($client),
            'notifications' => $items->map(fn ($n) => [
                'id_notification' => $n->id_notification,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'is_read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $client = CurrentClient::get();

        return response()->json([
            'status' => 'success',
            'unread_count' => $this->notifications->unreadCount($client),
        ]);
    }

    public function markRead(int $notificationId): JsonResponse
    {
        $client = CurrentClient::get();

        if (! $this->notifications->markRead($client, $notificationId)) {
            return response()->json(['status' => 'error', 'message' => 'Notification introuvable.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'unread_count' => $this->notifications->unreadCount($client),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $client = CurrentClient::get();
        $this->notifications->markAllRead($client);

        return response()->json([
            'status' => 'success',
            'unread_count' => 0,
        ]);
    }
}
