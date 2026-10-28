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
            // Android small icon native config (notification-icon.png) se aata hai;
            // color payload se taaki icon background brand terracotta rahe.
            'color' => '#C65D3A',
            'priority' => 'high',
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
     * Rider ko naya order assign hua.
     *
     * @return array{title: string, body: string}
     */
    public static function forRiderAssignment(int $orderId, string $customerName, ?string $address): array
    {
        $body = "Order #{$orderId} • {$customerName}";
        if ($address !== null && trim($address) !== '') {
            $body .= ' • '.mb_substr(trim($address), 0, 80);
        }

        return ['title' => 'New delivery assigned', 'body' => $body];
    }

    /**
     * Customer ko rider accept karne ki update — naam + vehicle + phone.
     *
     * @return array{title: string, body: string}
     */
    public static function forCustomerRiderAccepted(
        string $riderName,
        string $vehicleType,
        ?string $vehicleNumber,
        ?string $phone,
    ): array {
        $vehicle = trim($vehicleType.($vehicleNumber !== null && $vehicleNumber !== '' ? ' • '.$vehicleNumber : ''));
        $contact = $phone !== null && $phone !== '' ? " • 📞 {$phone}" : '';

        return [
            'title' => 'Rider assigned! 🛵',
            'body' => "{$riderName} ({$vehicle}) will deliver your order{$contact}.",
        ];
    }

    /**
     * Kitchen status → user-friendly Hindi-English push text.
     *
     * @return array{title: string, body: string}|null
     */
    public static function forFulfillment(int $orderId, int $total, string $status): ?array
    {
        return match ($status) {
            'preparing' => ['title' => 'Your food is being prepared! 👨‍🍳', 'body' => 'We have started preparing your order — it will be fresh and hot!'],
            'ready' => ['title' => 'Your order is packed! 🛵', 'body' => 'Your food is ready — a rider will reach you shortly.'],
            'out_for_delivery' => ['title' => 'Your rider is on the way! 🛵', 'body' => 'Your order is on its way — it will arrive in a few minutes.'],
            'arrived' => ['title' => 'Your rider has arrived!', 'body' => 'Your rider is at your door — please collect your order.'],
            'delivered' => ['title' => 'Enjoy your meal! 😋', 'body' => 'Your order has been delivered. Thank you for ordering from Dev Ratna!'],
            'cancelled' => ['title' => 'Order cancelled', 'body' => 'Your order has been cancelled. Your refund will be issued — call the shop if you need help.'],
            default => null,
        };
    }
}
