<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Expo Push Service (https://exp.host/--/api/v2/push/send).
 *
 * No Firebase setup needed — app expo-notifications se token leti hai,
 * backend yaha plain HTTPS POST karta hai. Kabhi throw nahi karta;
 * push fail ho to order flow nahi rukta, sirf log me entry.
 */
class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->pushTokens()->pluck('token')->all();
        $tokens = array_values(array_filter($tokens, fn ($t) => str_starts_with((string) $t, 'ExponentPushToken[')));

        // Debug proof: kaunsi push kitne installs pe ja rahi hai (ghost app pakadne ke liye).
        Log::info('DevRatna expo push targets.', [
            'user_id' => (int) $user->id,
            'token_count' => count($tokens),
            'tokens' => array_map(fn ($t) => mb_substr((string) $t, 0, 28).'…', $tokens),
        ]);

        if ($tokens === []) {
            return;
        }

        $this->send($tokens, $title, $body, $data, (int) $user->id);
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     */
    public function send(array $tokens, string $title, string $body, array $data = [], int $userId = 0): void
    {
        $messages = array_map(fn (string $to) => [
            'to' => $to,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'order_alert.wav',
            'channelId' => 'orders',
        ], $tokens);

        try {
            $headers = ['Accept' => 'application/json'];
            $accessToken = (string) config('services.expo.access_token', '');
            if ($accessToken !== '') {
                $headers['Authorization'] = 'Bearer '.$accessToken;
            }

            // Expo max 100 messages per request.
            foreach (array_chunk($messages, 100) as $chunk) {
                $resp = Http::withHeaders($headers)->timeout(10)->post(self::ENDPOINT, $chunk);
                if (! $resp->successful()) {
                    Log::warning('DevRatna expo push failed (http).', ['user_id' => $userId, 'status' => $resp->status()]);
                    continue;
                }
                foreach ((array) $resp->json('data', []) as $ticket) {
                    if (($ticket['status'] ?? '') === 'error') {
                        Log::warning('DevRatna expo push ticket error.', [
                            'user_id' => $userId,
                            'error' => $ticket['message'] ?? $ticket['details']['error'] ?? 'unknown',
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Kitchen status → user-friendly Hindi-English push text.
     *
     * @return array{title: string, body: string}|null
     */
    public static function forFulfillment(int $orderId, int $total, string $status): ?array
    {
        return match ($status) {
            'preparing' => ['title' => 'Khana ban raha hai! 👨‍🍳', 'body' => 'Humne aapka order kitchen me laga diya hai — fresh aur garam milega!'],
            'ready' => ['title' => 'Order pack ho gaya! 🛵', 'body' => 'Aapka khana taiyaar hai — rider jald pahunch raha hai.'],
            'out_for_delivery' => ['title' => 'Rider nikal gaya! 🛵', 'body' => 'Aapka order raste me hai — bas kuch hi minute me pahunch jayega.'],
            'delivered' => ['title' => 'Enjoy your meal! 😋', 'body' => 'Aapka order deliver ho gaya. Dev Ratna se khane ke liye shukriya!'],
            'cancelled' => ['title' => 'Order cancel ho gaya', 'body' => 'Aapka order cancel ho gaya hai. Paise refund ho jayenge — koi dikkat ho to dukkan pe call karein.'],
            default => null,
        };
    }
}
