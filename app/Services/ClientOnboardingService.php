<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;

/**
 * Création de compte client par le staff : mot de passe + OTP + email d'accueil.
 */
class ClientOnboardingService
{
    public function __construct(
        private ClientAuthService $clientAuth,
        private OtpService $otpService,
        private MailService $mail,
    ) {}

    /**
     * @return array{password_set: bool, otp: string, whatsapp_sent: bool, sms_sent: bool, mail_sent: bool, credentials_mail_sent: bool}
     */
    public function onboardStaffCreatedClient(Client $client): array
    {
        return $this->deliverAccessCredentials($client);
    }

    /**
     * Nouveau mot de passe + OTP + email d'accueil (réutilisable depuis la grille clients).
     *
     * @return array{password_set: bool, otp: string, whatsapp_sent: bool, sms_sent: bool, mail_sent: bool, credentials_mail_sent: bool}
     */
    public function resendAccessCredentials(Client $client): array
    {
        return $this->deliverAccessCredentials($client);
    }

    /**
     * @return array{password_set: bool, otp: string, whatsapp_sent: bool, sms_sent: bool, mail_sent: bool, credentials_mail_sent: bool}
     */
    private function deliverAccessCredentials(Client $client): array
    {
        $plainPassword = $this->generatePlainPassword();
        $this->clientAuth->setPassword($client, $plainPassword);

        $skipOtpMail = ! empty($client->email);
        $delivery = $this->otpService->issueForClient($client, skipMail: $skipOtpMail);

        $credentialsMailSent = false;
        if ($skipOtpMail) {
            $credentialsMailSent = $this->sendWelcomeEmail($client, $plainPassword, $delivery['otp']);
        }

        if (! $credentialsMailSent && ! $delivery['mail_sent'] && ! empty($client->email)) {
            Log::warning('Email client non envoyé (identifiants + OTP)', [
                'client_id' => $client->id_client,
                'email' => $client->email,
            ]);
        }

        return [
            'password_set' => true,
            'otp' => $delivery['otp'],
            'whatsapp_sent' => $delivery['whatsapp_sent'],
            'sms_sent' => $delivery['sms_sent'],
            'mail_sent' => $credentialsMailSent || $delivery['mail_sent'],
            'credentials_mail_sent' => $credentialsMailSent,
        ];
    }

    public function sendWelcomeEmail(Client $client, string $plainPassword, string $otp): bool
    {
        if (empty($client->email)) {
            return false;
        }

        $body = $this->mail->renderTemplate('mail_new_client.html', [
            'NOM' => $client->nom_complet,
            'TEL' => $client->tel ?: '—',
            'EMAIL' => $client->email ?: '—',
            'PASSWORD' => $plainPassword,
            'OTP' => $otp,
            'ANNEE' => date('Y'),
        ]);

        return $this->mail->send(
            $client->email,
            $client->nom_complet,
            'Vos identifiants de connexion Authentiq',
            $body
        );
    }

    private function generatePlainPassword(): string
    {
        return bin2hex(random_bytes(4));
    }
}
