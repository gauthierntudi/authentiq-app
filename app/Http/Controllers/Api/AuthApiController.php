<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthApiController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    public function login(Request $request): JsonResponse
    {
        $login = trim((string) $request->input('login', ''));
        $password = trim((string) $request->input('password', ''));

        if ($login === '' || $password === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Email/Phone et mot de passe requis',
            ]);
        }

        $user = User::query()
            ->where('email', $login)
            ->orWhere('tel', $login)
            ->first();

        if (! $user || ! password_verify($password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Identifiants incorrects',
            ]);
        }

        $request->session()->put('pending_login_user_id', $user->id_user);

        return response()->json([
            'status' => 'success',
            'message' => 'Identifiants valides',
            'user_id' => $user->id_user,
        ]);
    }

    public function sendLoginOtp(Request $request): JsonResponse
    {
        $user = $this->resolvePendingLoginUser($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session expirée, veuillez vous reconnecter',
            ]);
        }

        return $this->deliverLoginOtp($user);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $code = trim((string) $request->input('code_otp', ''));

        if (! $userId || $code === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Paramètres manquants',
            ]);
        }

        $user = $this->otpService->verifyForUser($userId, $code);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP invalide ou expiré',
            ]);
        }

        if (! in_array($user->role, ['admin', 'user'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ce compte ne peut pas accéder à l\'interface agent.',
            ], 403);
        }

        CurrentUser::loginFromModel($user);
        session()->forget('pending_login_user_id');

        return response()->json([
            'status' => 'success',
            'message' => 'OTP valide, session démarrée',
            'user' => session('user'),
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $user = $this->resolvePendingLoginUser($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Utilisateur invalide',
            ]);
        }

        return $this->deliverLoginOtp($user);
    }

    private function resolvePendingLoginUser(Request $request): ?User
    {
        $sessionUserId = (int) $request->session()->get('pending_login_user_id', 0);
        $requestUserId = (int) $request->input('user_id', 0);

        if ($sessionUserId > 0) {
            if ($requestUserId > 0 && $requestUserId !== $sessionUserId) {
                return null;
            }

            return User::query()->find($sessionUserId);
        }

        if ($requestUserId > 0) {
            return User::query()->find($requestUserId);
        }

        return null;
    }

    private function deliverLoginOtp(User $user): JsonResponse
    {
        $delivery = $this->otpService->issueForUser($user);

        if (! $delivery['whatsapp_sent'] && ! $delivery['mail_sent']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible d\'envoyer le code (WhatsApp / e-mail). Réessayez ou contactez l\'administrateur.',
                'whatsapp_sent' => false,
                'mail_sent' => false,
            ]);
        }

        $channels = [];
        if ($delivery['whatsapp_sent']) {
            $channels[] = 'WhatsApp';
        }
        if ($delivery['mail_sent']) {
            $channels[] = 'e-mail';
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Code envoyé par '.implode(' et ', $channels),
            'whatsapp_sent' => $delivery['whatsapp_sent'],
            'sms_sent' => $delivery['sms_sent'],
            'mail_sent' => $delivery['mail_sent'],
        ]);
    }
}
