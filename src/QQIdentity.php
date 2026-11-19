<?php

namespace HamCQ\QQAuth;

use Flarum\Foundation\ValidationException;
use Flarum\User\LoginProvider;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class QQIdentity
{
    public const PROVIDERS = ['QQ', 'qq'];

    public static function openId($value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 100 || preg_match('/[\x00-\x20\x7f]/', $value)) {
            throw new \UnexpectedValueException('Invalid QQ OpenID');
        }
        return $value;
    }

    public static function unionId($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || strlen($value) > 64 || preg_match('/[\x00-\x20\x7f]/', $value)) {
            throw new \UnexpectedValueException('Invalid QQ UnionID');
        }
        return $value;
    }

    public static function logIn(string $openId, ?string $unionId, ?ServerRequestInterface $request = null): ?User
    {
        $openId = self::openId($openId);
        $unionId = self::unionId($unionId);
        return (new User())->getConnection()->transaction(function () use ($openId, $unionId, $request) {
            $binding = LoginProvider::whereIn('provider', self::PROVIDERS)->where('identifier', $openId)->lockForUpdate()->first();
            $unionUser = $unionId === null ? null : User::where('qq_unionid', $unionId)->lockForUpdate()->first();
            if ($binding && $unionUser && (int) $binding->user_id !== (int) $unionUser->id) self::conflict();
            $user = $binding ? User::where('id', $binding->user_id)->lockForUpdate()->firstOrFail() : $unionUser;
            if (!$user) return null;
            self::attach($user, $openId, $unionId, $binding, $request);
            return $user;
        }, 3);
    }

    public static function link(User $actor, string $openId, ?string $unionId, ?ServerRequestInterface $request = null): void
    {
        $actor->assertRegistered();
        $openId = self::openId($openId);
        $unionId = self::unionId($unionId);
        $actor->getConnection()->transaction(function () use ($actor, $openId, $unionId, $request) {
            $user = User::where('id', $actor->id)->lockForUpdate()->firstOrFail();
            $binding = LoginProvider::whereIn('provider', self::PROVIDERS)->where('identifier', $openId)->lockForUpdate()->first();
            if ($binding && (int) $binding->user_id !== (int) $user->id) self::conflict();
            // Do not let concurrent callbacks bind different QQ accounts to the same user.
            if (!$binding && $user->loginProviders()->whereIn('provider', self::PROVIDERS)->exists()) self::conflict();
            self::attach($user, $openId, $unionId, $binding, $request);
        }, 3);
    }

    private static function attach(User $user, string $openId, ?string $unionId, ?LoginProvider $binding, ?ServerRequestInterface $request): void
    {
        if ($unionId !== null) {
            if ($user->qq_unionid !== null && $user->qq_unionid !== '' && $user->qq_unionid !== $unionId) self::conflict();
            if (User::where('qq_unionid', $unionId)->where('id', '<>', $user->id)->exists()) self::conflict();
            if ($user->qq_unionid !== $unionId) {
                $user->qq_unionid = $unionId;
                $user->save();
            }
        }
        if ($binding) $binding->touch();
        else {
            $binding = $user->loginProviders()->create(['provider' => 'QQ', 'identifier' => $openId]);
            BindingHistory::record($binding, 'bind', $request);
        }
    }

    public static function unlink(User $actor, ?ServerRequestInterface $request = null): bool
    {
        $actor->assertRegistered();
        return $actor->getConnection()->transaction(function () use ($actor, $request) {
            $user = User::where('id', $actor->id)->lockForUpdate()->firstOrFail();
            $bindings = $user->loginProviders()->whereIn('provider', self::PROVIDERS)->lockForUpdate()->get();
            foreach ($bindings as $binding) {
                BindingHistory::record($binding, 'unbind', $request);
                $binding->delete();
            }
            $hasUnionId = $user->qq_unionid !== null && $user->qq_unionid !== '';
            if ($hasUnionId) {
                if ($bindings->isEmpty()) BindingHistory::recordUnionUnlink($user, $request);
                $user->qq_unionid = null;
                $user->save();
            }
            return $bindings->isNotEmpty() || $hasUnionId;
        }, 3);
    }

    public static function status(User $user): array
    {
        $qqCount = $user->loginProviders()->whereIn('provider', self::PROVIDERS)->count();
        $linked = $qqCount > 0 || ($user->qq_unionid !== null && $user->qq_unionid !== '');
        return [
            'isLinked' => $linked,
            'identifier' => null,
            'providersCount' => $user->loginProviders()->count() - $qqCount + ($linked ? 1 : 0),
        ];
    }

    public static function conflict(): void
    {
        throw new ValidationException(['qq' => resolve(TranslatorInterface::class)->trans('hamcq-auth-qq.forum.alerts.identity_conflict')]);
    }
}
