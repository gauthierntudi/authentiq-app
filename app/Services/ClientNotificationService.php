<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientNotification;
use Illuminate\Support\Collection;

class ClientNotificationService
{
    public function notify(Client $client, string $type, string $title, ?string $body = null): ClientNotification
    {
        return ClientNotification::query()->create([
            'id_client' => $client->id_client,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }

    /** @return Collection<int, ClientNotification> */
    public function listForClient(Client $client, int $limit = 50): Collection
    {
        return ClientNotification::query()
            ->where('id_client', $client->id_client)
            ->orderByDesc('id_notification')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(Client $client): int
    {
        return ClientNotification::query()
            ->where('id_client', $client->id_client)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(Client $client, int $notificationId): bool
    {
        $n = ClientNotification::query()
            ->where('id_notification', $notificationId)
            ->where('id_client', $client->id_client)
            ->first();

        if (! $n) {
            return false;
        }

        if (! $n->read_at) {
            $n->update(['read_at' => now()]);
        }

        return true;
    }

    public function markAllRead(Client $client): int
    {
        return ClientNotification::query()
            ->where('id_client', $client->id_client)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
