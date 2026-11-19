<?php

namespace HamCQ\QQAuth;

use Flarum\Http\RequestUtil;
use Flarum\User\LoginProvider;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BindingHistory implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->registrationToken($request);
        if (!$token) return $handler->handle($request);
        $db = (new LoginProvider())->getConnection();
        $level = $db->transactionLevel();
        $db->beginTransaction();
        try {
            $response = $handler->handle($request);
            if ($response->getStatusCode() >= 400) $db->rollBack($level);
            else {
                // Core creates the first binding during registration; record it explicitly here.
                $binding = LoginProvider::where('provider', $token->provider)->where('identifier', $token->identifier)->firstOrFail();
                self::record($binding, 'bind', $request);
                $db->commit();
            }
            return $response;
        } catch (\Throwable $error) {
            $db->rollBack($level);
            throw $error;
        }
    }

    private function registrationToken(ServerRequestInterface $request): ?RegistrationToken
    {
        if ($request->getMethod() !== 'POST' || $request->getAttribute('routeName') !== 'users.create') return null;
        $body = $request->getParsedBody();
        $value = is_array($body) ? ($body['data']['attributes']['token'] ?? null) : null;
        $token = is_string($value) ? RegistrationToken::where('token', $value)->first() : null;
        return $token && in_array($token->provider, QQIdentity::PROVIDERS, true) ? $token : null;
    }

    public static function recordUnionUnlink(User $user, ?ServerRequestInterface $request = null): void
    {
        $binding = new LoginProvider();
        $binding->setConnection($user->getConnectionName());
        $binding->user_id = $user->id;
        $binding->provider = 'QQ';
        self::record($binding, 'unbind', $request);
    }

    public static function record(LoginProvider $binding, string $action, ?ServerRequestInterface $request = null): void
    {
        if (!in_array($binding->provider, QQIdentity::PROVIDERS, true)) return;
        $db = $binding->getConnection();
        $ip = $request ? $request->getAttribute('ipAddress') : null;
        if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = $request ? ($request->getServerParams()['REMOTE_ADDR'] ?? null) : null;
        }
        if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) $ip = null;
        $actor = $request && $request->getAttribute('actorReference')
            ? RequestUtil::getActor($request) : ($request ? $request->getAttribute('actor') : null);
        $db->table('qq_binding_history')->insert([
            'user_id' => $binding->user_id,
            'actor_id' => $actor instanceof User && $actor->id ? $actor->id : null,
            'binding_id' => $binding->id,
            'provider' => $binding->provider,
            'identifier' => $binding->identifier,
            'union_id' => $db->table('users')->where('id', $binding->user_id)->value('qq_unionid'),
            'action' => $action,
            'ip' => $ip,
            'bound_at' => $binding->created_at,
            'created_at' => $binding->freshTimestampString(),
        ]);
    }
}
