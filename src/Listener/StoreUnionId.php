<?php

namespace HamCQ\QQAuth\Listener;

use Flarum\User\Event\RegisteringFromProvider;
use Flarum\User\User;
use HamCQ\QQAuth\QQIdentity;

class StoreUnionId
{
    public function __invoke(RegisteringFromProvider $event): void
    {
        if (!in_array($event->provider, QQIdentity::PROVIDERS, true)) return;
        // Only the server-side registration-token payload is trusted, never request attributes.
        $identity = $event->payload['_hamcq_qq_identity'] ?? null;
        if (!is_array($identity)) return;
        QQIdentity::openId($identity['openid'] ?? null);
        $unionId = QQIdentity::unionId($identity['unionid'] ?? null);
        if ($unionId === null) return;
        if (User::where('qq_unionid', $unionId)->exists()) QQIdentity::conflict();
        $event->user->qq_unionid = $unionId;
    }
}
