<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public function renderTemplate(string $templateName, array $data): string
    {
        $path = resource_path('views/emails-legacy/'.$templateName);

        if (! file_exists($path)) {
            Log::error('Template email introuvable', ['path' => $path]);

            return '';
        }

        $content = file_get_contents($path);

        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $content);
        }

        return $content;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if ($htmlBody === '') {
            return false;
        }

        try {
            Mail::html($htmlBody, function ($message) use ($toEmail, $toName, $subject) {
                $message->to($toEmail, $toName)
                    ->subject($subject)
                    ->from(
                        config('authentiq.mail.from_address') ?: config('mail.from.address'),
                        config('authentiq.mail.from_name') ?: config('mail.from.name')
                    );
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Erreur envoi email', ['to' => $toEmail, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
