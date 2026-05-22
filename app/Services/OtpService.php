<?php

namespace App\Services;

use App\Models\Client;
use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;

class OtpService
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private MailService $mail,
    ) {}

    public function issueForUser(User $user): array
    {
        $otp = $this->whatsApp->generateOtp();
        $expireAt = Carbon::now()->addMinutes(config('authentiq.otp_ttl_minutes', 10));

        OtpCode::query()
            ->where('user_type', 'user')
            ->where('user_id', $user->id_user)
            ->delete();

        OtpCode::query()->create([
            'user_type' => 'user',
            'user_id' => $user->id_user,
            'code_otp' => $otp,
            'expire_at' => $expireAt,
        ]);

        $whatsappSent = false;
        if (! empty($user->tel)) {
            $whatsappSent = $this->whatsApp->sendOtp($user->tel, $otp);
        }

        $mailSent = false;
        if (! empty($user->email)) {
            $body = $this->mail->renderTemplate('otp_email_login.html', [
                'USER_NAME' => $user->nom_complet,
                'OTP' => $otp,
                'ANNEE' => date('Y'),
            ]);
            $mailSent = $this->mail->send(
                $user->email,
                $user->nom_complet,
                'Votre code OTP Authentiq',
                $body
            );
        }

        return [
            'otp' => $otp,
            'whatsapp_sent' => $whatsappSent,
            'sms_sent' => $whatsappSent,
            'mail_sent' => $mailSent,
        ];
    }

    public function verifyForUser(int $userId, string $code): ?User
    {
        $otp = OtpCode::query()
            ->where('user_type', 'user')
            ->where('user_id', $userId)
            ->where('code_otp', $code)
            ->first();

        if (! $otp) {
            return null;
        }

        if ($otp->expire_at->isPast()) {
            return null;
        }

        $otp->delete();

        return User::query()->find($userId);
    }

    public function issueForClient(Client $client, bool $skipMail = false): array
    {
        $otp = $this->whatsApp->generateOtp();
        $expireAt = Carbon::now()->addMinutes(config('authentiq.otp_ttl_minutes', 10));

        OtpCode::query()
            ->where('user_type', 'client')
            ->where('client_id', $client->id_client)
            ->delete();

        OtpCode::query()->create([
            'user_type' => 'client',
            'client_id' => $client->id_client,
            'code_otp' => $otp,
            'expire_at' => $expireAt,
        ]);

        $whatsappSent = false;
        if (! empty($client->tel)) {
            $whatsappSent = $this->whatsApp->sendOtp($client->tel, $otp);
        }

        $mailSent = false;
        if (! $skipMail && ! empty($client->email)) {
            $body = $this->mail->renderTemplate('otp_email.html', [
                'OTP' => $otp,
                'ANNEE' => date('Y'),
            ]);
            $mailSent = $this->mail->send(
                $client->email,
                $client->nom_complet,
                'Votre code de vérification',
                $body
            );
        }

        return [
            'otp' => $otp,
            'whatsapp_sent' => $whatsappSent,
            'sms_sent' => $whatsappSent,
            'mail_sent' => $mailSent,
        ];
    }

    public function verifyClientOtp(int $clientId, string $code): bool
    {
        $otp = OtpCode::query()
            ->where('user_type', 'client')
            ->where('client_id', $clientId)
            ->where('code_otp', $code)
            ->orderByDesc('created_at')
            ->first();

        if (! $otp || $otp->expire_at->isPast()) {
            return false;
        }

        $otp->delete();

        Client::query()
            ->where('id_client', $clientId)
            ->update(['is_active' => 1]);

        return true;
    }
}
