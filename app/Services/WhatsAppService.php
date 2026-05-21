<?php

namespace App\Services;

class WhatsAppService
{
    public function generateOtp(): string
    {
        $otp = random_int(100000000, 999999999);
        $otpString = (string) $otp;
        $startIndex = random_int(0, 3);

        return substr($otpString, $startIndex, 6);
    }

    public function formatPhoneE164(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+243'.substr($phone, 1);
        }

        if (str_starts_with($phone, '243')) {
            return '+'.$phone;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return '+243'.$phone;
    }

    /**
     * Envoie l'OTP via WhatsApp (template Twilio whatsapp/authentication).
     *
     * Meta/Twilio imposent le texte (« X is your verification code… ») et réservent
     * une variable implicite {{1}} pour le code. Elle n'apparaît pas dans l'éditeur
     * mais doit être fournie à l'envoi : ContentVariables={"1":"403239"}.
     *
     * @see https://www.twilio.com/docs/content/whatsappauthentication
     */
    public function sendOtp(string $toPhone, string $otp): bool
    {
        $sid = config('authentiq.twilio.sid');
        $token = config('authentiq.twilio.token');
        $sender = config('authentiq.whatsapp.sender');
        $templateSid = config('authentiq.whatsapp.template_sid');

        if (! $sid || ! $token || ! $sender || ! $templateSid) {
            logger()->warning('Twilio WhatsApp non configuré — message non envoyé', ['to' => $toPhone]);

            return false;
        }

        if (strlen($otp) > 15) {
            logger()->error('OTP trop long pour template WhatsApp Authentication (max 15 caractères)', ['length' => strlen($otp)]);

            return false;
        }

        $to = 'whatsapp:'.$this->formatPhoneE164($toPhone);
        $from = 'whatsapp:'.$this->formatPhoneE164($sender);

        // Variable {{1}} implicite sur tous les templates Authentication Twilio
        $variableKey = (string) config('authentiq.whatsapp.otp_variable_key', '1');
        $contentVariables = json_encode([$variableKey => $otp], JSON_UNESCAPED_UNICODE);

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$sid}:{$token}",
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $from,
                'To' => $to,
                'ContentSid' => $templateSid,
                'ContentVariables' => $contentVariables,
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            logger()->info('WhatsApp OTP envoyé', ['to' => $to]);

            return true;
        }

        logger()->error('WhatsApp OTP error', [
            'to' => $to,
            'http' => $httpCode,
            'response' => $response,
        ]);

        return false;
    }
}
