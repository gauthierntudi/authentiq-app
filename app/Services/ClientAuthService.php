<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientApiToken;
use Illuminate\Support\Str;

class ClientAuthService
{
    public function __construct(private OtpService $otpService) {}

    public function issueToken(Client $client, string $name = 'mobile'): array
    {
        $plain = Str::random(64);
        $ttlDays = (int) config('authentiq.client_token_ttl_days', 30);

        ClientApiToken::query()->create([
            'id_client' => $client->id_client,
            'token_hash' => hash('sha256', $plain),
            'name' => $name,
            'expires_at' => now()->addDays($ttlDays),
        ]);

        return [
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_in_days' => $ttlDays,
        ];
    }

    public function resolveFromBearer(?string $authorization): ?Client
    {
        if (! $authorization || ! str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $plain = trim(substr($authorization, 7));
        if ($plain === '') {
            return null;
        }

        $record = ClientApiToken::query()
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        if (! $record || $record->isExpired()) {
            return null;
        }

        $record->update(['last_used_at' => now()]);

        return Client::query()->find($record->id_client);
    }

    public function revokeAllTokens(Client $client): void
    {
        ClientApiToken::query()->where('id_client', $client->id_client)->delete();
    }

    public function setPassword(Client $client, string $password): void
    {
        $client->password = password_hash($password, PASSWORD_DEFAULT);
        $client->save();
    }

    public function verifyPassword(Client $client, string $password): bool
    {
        return $client->password && password_verify($password, $client->password);
    }

    public function issueOtp(Client $client): array
    {
        return $this->otpService->issueForClient($client);
    }

    public function verifyOtp(int $clientId, string $code): ?Client
    {
        if (! $this->otpService->verifyClientOtp($clientId, $code)) {
            return null;
        }

        $client = Client::query()->find($clientId);

        if ($client && ! $client->mobile_registered_at) {
            $client->update(['mobile_registered_at' => now()]);
        }

        return $client;
    }
}
