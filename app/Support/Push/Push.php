<?php

namespace App\Support\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Settings;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Mitteilungen aufs Handy (Web-Push, wie im Telemaster-Portal). Die Schlüssel (VAPID) legt das CRM beim ersten Bedarf selbst an
 * und speichert sie in den Einstellungen (privater Schlüssel verschlüsselt). Auf dem iPhone/iPad nur in der installierten App.
 */
class Push
{
    /** Für Tests: gesetzt → Mitteilungen werden hier gesammelt statt verschickt ([user_id, Nachricht]). */
    public static ?array $fake = null;

    /** @return array{public: string, private: string}|null */
    private static ?array $memo = null;

    private static bool $failed = false;

    public static function keys(): ?array
    {
        if (self::$memo !== null || self::$failed) {
            return self::$memo;
        }
        try {
            $pub = Settings::get('push_public_key');
            $enc = Settings::get('push_private_key');
            if ($pub && $enc) {
                return self::$memo = ['public' => $pub, 'private' => Crypt::decryptString($enc)];
            }
            $k = VAPID::createVapidKeys();
            Settings::putMany(['push_public_key' => $k['publicKey'], 'push_private_key' => Crypt::encryptString($k['privateKey'])]);

            return self::$memo = ['public' => $k['publicKey'], 'private' => $k['privateKey']];
        } catch (Throwable $e) {
            self::$failed = true;
            report($e);

            return null;
        }
    }

    public static function publicKey(): ?string
    {
        return self::keys()['public'] ?? null;
    }

    public static function hasDevice(User $user): bool
    {
        try {
            return Schema::hasTable('push_subscriptions') && PushSubscription::query()->where('user_id', $user->id)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{title: string, body?: ?string, url?: ?string, tag?: ?string}  $message
     * @return int Anzahl Geräte, an die gesendet wurde
     */
    public static function toUser(User $user, array $message): int
    {
        if (self::$fake !== null) {
            self::$fake[] = [$user->id, $message];

            return 1;
        }
        if (! Schema::hasTable('push_subscriptions')) {
            return 0;
        }
        $subs = PushSubscription::query()->where('user_id', $user->id)->get();
        $keys = $subs->isEmpty() ? null : self::keys();
        if ($keys === null) {
            return 0;
        }
        $from = \App\Support\Mail\MailSettings::get('mail_from_address');
        $push = new WebPush(['VAPID' => [
            'subject' => $from !== '' ? 'mailto:'.$from : rtrim((string) config('app.url'), '/'),
            'publicKey' => $keys['public'],
            'privateKey' => $keys['private'],
        ]], ['TTL' => 3600 * 12, 'urgency' => 'high'], new Client(['timeout' => 10, 'connect_timeout' => 5]), logger: app('log'));

        $payload = json_encode([
            'title' => mb_substr(strip_tags($message['title']), 0, 120),
            'body' => mb_substr(trim(strip_tags((string) ($message['body'] ?? ''))), 0, 240),
            'url' => $message['url'] ?? route('tag.index'),
            'tag' => $message['tag'] ?? null,
        ]);
        foreach ($subs as $sub) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint, 'publicKey' => $sub->public_key, 'authToken' => $sub->auth_token, 'contentEncoding' => $sub->content_encoding,
            ]), $payload);
        }
        $sent = 0;
        try {
            foreach ($push->flush() as $report) {
                $hash = hash('sha256', $report->getEndpoint());
                if ($report->isSuccess()) {
                    $sent++;
                    PushSubscription::query()->where('endpoint_hash', $hash)->update(['last_used_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', $hash)->delete(); // App gelöscht oder Mitteilungen aus
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $sent;
    }
}
