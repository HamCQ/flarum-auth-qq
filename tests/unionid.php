<?php
// Uses an in-memory SQLite database and Guzzle's HTTP mock. Never loads config.php.
$root = dirname(__DIR__, 3);
$loader = require $root.'/vendor/autoload.php';
if (!class_exists(League\OAuth2\Client\Provider\AbstractProvider::class)) {
    $oauthAutoload = getenv('QQ_TEST_OAUTH_AUTOLOAD');
    if (!$oauthAutoload || !is_file($oauthAutoload)) throw new RuntimeException('Install the declared OAuth dependency or set QQ_TEST_OAUTH_AUTOLOAD');
    require $oauthAutoload;
    $loader->unregister(); $loader->register(true);
}
$loader->addPsr4('HamCQ\\QQAuth\\', dirname(__DIR__).'/src/');

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\Command\RegisterUser;
use Flarum\User\Command\RegisterUserHandler;
use Flarum\User\Event\RegisteringFromProvider;
use Flarum\User\Guest;
use Flarum\User\LoginProvider;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use HamCQ\QQAuth\QQIdentity;
use HamCQ\QQAuth\Listener\StoreUnionId;
use HamCQ\QQAuth\Api\Controllers\QQController;
use HamCQ\QQAuth\Api\Controllers\QQUnlinkController;
use HamCQ\QQAuth\Http\Controllers\QQResponseFactory;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;

$checks = 0;
function qqCheck($ok, string $message): void {
    ++$GLOBALS['checks'];
    if (!$ok) throw new RuntimeException($message);
}
function qqReject(callable $callback, string $type): void {
    try { $callback(); } catch (Throwable $error) {
        qqCheck($error instanceof $type, 'Expected '.$type.', got '.get_class($error).': '.$error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: '.$type);
}
function qqProvider(array $responses, array &$history): QQController {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new QQController(['clientId' => 'fixture-app', 'secret' => 'fixture-secret'], [
        'httpClient' => new GuzzleHttp\Client(['handler' => $stack]),
    ]);
}
function qqResponse(array $data, int $status = 200): Response {
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
}
function qqUser(string $name): User {
    $user = new User();
    $user->username = $name; $user->email = $name.'@example.test';
    $user->save();
    return $user;
}

$container = new Container(); Container::setInstance($container);
$translator = new Flarum\Locale\Translator('en');
$translator->setFallbackLocales(['en']);
$translator->addLoader('yaml', new Symfony\Component\Translation\Loader\YamlFileLoader());
foreach (['en', 'zh'] as $locale) $translator->addResource('yaml', dirname(__DIR__).'/resources/locale/'.$locale.'.yml', $locale, 'messages+intl-icu');
$container->instance(Symfony\Contracts\Translation\TranslatorInterface::class, $translator);
$container->instance('translator', $translator);
$container->instance(Illuminate\Contracts\Cache\Store::class, new Illuminate\Cache\ArrayStore());
$events = new Illuminate\Events\Dispatcher($container);
$container->instance('events', $events);
$capsule = new Manager($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
$capsule->setEventDispatcher($events); $capsule->setAsGlobal(); $capsule->bootEloquent();
$db = $capsule->getConnection();
$db->getPdo()->sqliteCreateCollation('utf8mb4_bin', 'strcmp');
$schema = $db->getSchemaBuilder();
$schema->create('groups', function (Blueprint $table) {
    $table->increments('id');
});
$schema->create('users', function (Blueprint $table) {
    $table->increments('id'); $table->string('username')->unique(); $table->string('email')->unique();
    $table->string('password')->nullable(); $table->dateTime('joined_at')->nullable();
    $table->boolean('is_email_confirmed')->default(false); $table->text('preferences')->nullable();
});
$schema->create('login_providers', function (Blueprint $table) {
    $table->increments('id'); $table->unsignedInteger('user_id');
    $table->string('provider', 100); $table->string('identifier', 100);
    $table->dateTime('created_at')->nullable(); $table->dateTime('last_login_at')->nullable();
    $table->unique(['provider', 'identifier']);
});
$schema->create('registration_tokens', function (Blueprint $table) {
    $table->string('token', 40)->primary(); $table->string('provider'); $table->string('identifier');
    $table->text('user_attributes'); $table->text('payload'); $table->dateTime('created_at');
});
$legacy = qqUser('legacy');
$legacyBinding = $legacy->loginProviders()->create(['provider' => 'QQ', 'identifier' => 'old-openid']);
$lower = qqUser('lowercase'); $lower->loginProviders()->create(['provider' => 'qq', 'identifier' => 'lower-openid']);
$original = $db->table('login_providers')->get()->toJson();
$migration = require dirname(__DIR__).'/migrations/2026_11_19_000000_add_qq_unionid.php';
$migration['up']($schema); $migration['up']($schema);
$mysql = new Illuminate\Database\MySqlConnection(null, 'qq_test', 'prefix_');
$blueprint = new Blueprint('users');
$blueprint->string('qq_unionid', 64)->nullable()->charset('utf8mb4')->collation('utf8mb4_bin');
$blueprint->unique('qq_unionid', 'users_qq_unionid_unique');
$mysqlSql = implode("\n", $blueprint->toSql($mysql, $mysql->withTablePrefix(new Illuminate\Database\Schema\Grammars\MySqlGrammar())));
qqCheck(str_contains($mysqlSql, '`prefix_users`') && str_contains($mysqlSql, 'varchar(64) character set utf8mb4 collate') && str_contains($mysqlSql, 'utf8mb4_bin') && str_contains($mysqlSql, ' null'), 'Unexpected MySQL column SQL: '.$mysqlSql);
qqCheck(str_contains($mysqlSql, 'unique `users_qq_unionid_unique`(`qq_unionid`)'), 'MySQL schema enforces unique UnionID ownership');
qqCheck($db->table('login_providers')->get()->toJson() === $original, 'Migration never rewrites existing OpenIDs');
qqCheck(User::whereNotNull('qq_unionid')->count() === 0, 'Legacy users start with NULL UnionIDs');
qqCheck(QQIdentity::logIn('old-openid', null)->id === $legacy->id, 'Old user can log in without UnionID');
qqCheck(QQIdentity::logIn('lower-openid', null)->id === $lower->id, 'Legacy lowercase provider is supported');
qqCheck(QQIdentity::logIn('old-openid', 'union-legacy')->id === $legacy->id, 'Existing OpenID determines the old account');
qqCheck($legacy->fresh()->qq_unionid === 'union-legacy' && LoginProvider::find($legacyBinding->id)->identifier === 'old-openid', 'Backfill UnionID without replacing or deleting OpenID');
qqCheck(QQIdentity::logIn('new-app-openid', 'union-legacy')->id === $legacy->id, 'UnionID maps another verified OpenID to the same user');
qqCheck(QQIdentity::logIn('new-app-openid', null)->id === $legacy->id, 'Saved OpenID still works if UnionID is missing on a later login');
qqCheck(User::count() === 2, 'UnionID login never creates another account');
$legacy->loginProviders()->create(['provider' => 'github', 'identifier' => 'github-legacy']);
$status = QQIdentity::status($legacy->fresh());
qqCheck($status === ['isLinked' => true, 'identifier' => null, 'providersCount' => 2], 'Multiple QQ OpenIDs count as one login method and IDs remain private');
$other = qqUser('other'); QQIdentity::link($other, 'other-openid', 'union-other');
$beforeConflict = [$db->table('users')->get()->toJson(), $db->table('login_providers')->get()->toJson()];
qqReject(fn() => QQIdentity::logIn('old-openid', 'union-other'), ValidationException::class);
qqReject(fn() => QQIdentity::logIn('old-openid', 'different-union'), ValidationException::class);
qqReject(fn() => QQIdentity::link($other, 'old-openid', 'union-other'), ValidationException::class);
qqCheck([$db->table('users')->get()->toJson(), $db->table('login_providers')->get()->toJson()] === $beforeConflict, 'Conflicts roll back without moving, merging or replacing identities');
$fresh = qqUser('fresh');
qqReject(fn() => QQIdentity::link($fresh, 'unused-openid', 'union-legacy'), ValidationException::class);
qqCheck($fresh->fresh()->qq_unionid === null && !$fresh->loginProviders()->exists(), 'A claimed UnionID cannot be bound to another account');
QQIdentity::link($fresh, 'fresh-openid', 'union-fresh');
qqReject(fn() => QQIdentity::link($fresh, 'second-qq-account', 'union-second'), ValidationException::class);
qqCheck(!$fresh->loginProviders()->where('identifier', 'second-qq-account')->exists(), 'A second concurrent-style bind cannot add a different QQ account');
qqReject(fn() => QQIdentity::link(new Guest(), 'guest-openid', null), Flarum\User\Exception\NotAuthenticatedException::class);
qqReject(fn() => QQIdentity::unlink(new Guest()), Flarum\User\Exception\NotAuthenticatedException::class);

$history = []; $token = new AccessToken(['access_token' => 'fixture-access-token']);
$provider = qqProvider([qqResponse(['client_id' => 'fixture-app', 'openid' => 'legacy-without-union'])], $history);
qqCheck($provider->fetchOpenId($token)['unionid'] === null, 'Successful OpenID-only responses remain valid');
parse_str($history[0]['request']->getUri()->getQuery(), $params);
qqCheck($params['unionid'] === '1' && $params['fmt'] === 'json', 'Request UnionID using the documented optional parameter');
$history = [];
$provider = qqProvider([qqResponse(['client_id' => 'fixture-app', 'openid' => 'verified-openid', 'unionid' => 'verified-union'])], $history);
qqCheck($provider->fetchOpenId($token)['unionid'] === 'verified-union', 'Read UnionID only from the QQ server response');
foreach ([200, 400] as $httpStatus) {
    $history = [];
    $provider = qqProvider([qqResponse(['error' => 100048], $httpStatus), qqResponse(['client_id' => 'fixture-app', 'openid' => 'permission-fallback'])], $history);
    qqCheck($provider->fetchOpenId($token)['openid'] === 'permission-fallback' && count($history) === 2, 'Missing UnionID permission falls back to old OpenID login');
    parse_str($history[1]['request']->getUri()->getQuery(), $fallbackParams);
    qqCheck(!isset($fallbackParams['unionid']), 'Fallback removes only the UnionID option');
}
$history = []; $provider = qqProvider([qqResponse(['error' => 100016])], $history);
qqReject(fn() => $provider->fetchOpenId($token), League\OAuth2\Client\Provider\Exception\IdentityProviderException::class);
qqCheck(count($history) === 1, 'Invalid access tokens are not hidden by fallback retries');
foreach ([
    ['client_id' => 'another-app', 'openid' => 'wrong-app'],
    ['client_id' => 'fixture-app', 'openid' => true],
    ['client_id' => 'fixture-app', 'openid' => ''],
    ['client_id' => 'fixture-app', 'openid' => 'valid', 'unionid' => true],
    ['client_id' => 'fixture-app', 'openid' => 'valid', 'unionid' => str_repeat('x', 65)],
] as $invalid) {
    $history = []; $provider = qqProvider([qqResponse($invalid)], $history);
    qqReject(fn() => $provider->fetchOpenId($token), UnexpectedValueException::class);
}
$history = []; $provider = qqProvider([new Response(200, [], 'callback( {"client_id":"fixture-app","openid":"jsonp-legacy"} );')], $history);
qqCheck($provider->fetchOpenId($token)['openid'] === 'jsonp-legacy', 'Legacy callback response format remains readable');
$history = []; $provider = qqProvider([qqResponse(['ret' => 0, 'nickname' => 'Legacy nickname'])], $history);
qqCheck($provider->getResourceOwnerDetailsUrl($token, 'verified-openid')['nickname'] === 'Legacy nickname', 'Existing nickname and avatar request path remains compatible');
$history = []; $provider = qqProvider([qqResponse(['ret' => 100016])], $history);
qqReject(fn() => $provider->getResourceOwnerDetailsUrl($token, 'verified-openid'), League\OAuth2\Client\Provider\Exception\IdentityProviderException::class);

$listener = new StoreUnionId();
$events->listen(RegisteringFromProvider::class, $listener);
$settings = new class implements Flarum\Settings\SettingsRepositoryInterface {
    public function all(): array { return ['allow_sign_up' => true]; }
    public function get($key, $default = null) { return $key === 'allow_sign_up' ? true : $default; }
    public function set($key, $value) {}
    public function delete($keyLike) {}
};
$container->instance(Flarum\Settings\SettingsRepositoryInterface::class, $settings);
$container->instance(Illuminate\Contracts\Hashing\Hasher::class, new Illuminate\Hashing\BcryptHasher(['rounds' => 4]));
User::setHasher($container->make(Illuminate\Contracts\Hashing\Hasher::class));
$validator = new Illuminate\Validation\Factory($translator, $container);
$validator->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($capsule->getDatabaseManager()));
$avatar = new class extends Flarum\User\AvatarUploader { public function __construct() {} };
// Keep core validation rules without scanning the site's installed extensions.
$userValidator = new class($validator, $translator) extends Flarum\User\UserValidator {
    protected function getClassExtensionId(): ?string { return null; }
};
$registrar = new RegisterUserHandler($events, $settings, $userValidator, $avatar, $validator, new Intervention\Image\ImageManager());
$factory = new class extends QQResponseFactory {
    public ?int $loggedUser = null;
    public function __construct() {}
    public function makeLoggedInResponse(User $user, bool $isMobile = false, string $url = '') {
        $this->loggedUser = $user->id;
        return new Laminas\Diactoros\Response\HtmlResponse('logged-in');
    }
};
$reply = $factory->make('QQ', 'register-openid', function ($registration) {
    $registration->suggestUsername('registered')->setPayload(['nickname' => 'Registered']);
}, false, '', 'union-register');
$issued = RegistrationToken::where('identifier', 'register-openid')->firstOrFail();
qqCheck($issued->identifier === 'register-openid' && $issued->payload['_hamcq_qq_identity']['unionid'] === 'union-register', 'Registration token retains OpenID and trusted UnionID metadata');
qqCheck(!str_contains((string) $reply->getBody(), 'union-register') && !str_contains((string) $reply->getBody(), 'qq_unionid'), 'UnionID is not returned in the registration popup payload');
$registered = $registrar->handle(new RegisterUser(new Guest(), ['attributes' => [
    'username' => 'registered', 'email' => 'registered@example.test', 'token' => $issued->token,
    'qq_unionid' => 'forged-request-union',
]]));
qqCheck($registered->qq_unionid === 'union-register', 'Real Flarum registration saves the trusted UnionID, ignoring the forged request attribute');
qqCheck($registered->loginProviders()->where('provider', 'QQ')->value('identifier') === 'register-openid', 'Core registration still stores the original OpenID');
$factory->make('QQ', 'register-openid', fn($registration) => null, false, '', 'union-register');
qqCheck($factory->loggedUser === $registered->id, 'Newly registered QQ account can log in normally');
$oldToken = RegistrationToken::generate('QQ', 'pre-upgrade-token-openid', [], ['nickname' => 'Old token']); $oldToken->save();
$oldSignup = $registrar->handle(new RegisterUser(new Guest(), ['attributes' => [
    'username' => 'oldsignup', 'email' => 'oldsignup@example.test', 'token' => $oldToken->token,
]]));
qqCheck($oldSignup->qq_unionid === null && $oldSignup->loginProviders()->value('identifier') === 'pre-upgrade-token-openid', 'Registration tokens issued before upgrading remain usable');
$factory->make('QQ', 'openid-only-signup', fn($registration) => null);
$openidOnlyToken = RegistrationToken::where('identifier', 'openid-only-signup')->firstOrFail();
$openidOnlySignup = $registrar->handle(new RegisterUser(new Guest(), ['attributes' => [
    'username' => 'openidonly', 'email' => 'openidonly@example.test', 'token' => $openidOnlyToken->token,
]]));
qqCheck($openidOnlySignup->qq_unionid === null && QQIdentity::logIn('openid-only-signup', null)->id === $openidOnlySignup->id, 'OpenID-only applications still support new registration and subsequent login');
$conflictToken = RegistrationToken::generate('QQ', 'competing-openid', [], ['_hamcq_qq_identity' => ['openid' => 'competing-openid', 'unionid' => 'union-register']]); $conflictToken->save();
$usersBefore = User::count();
qqReject(fn() => $registrar->handle(new RegisterUser(new Guest(), ['attributes' => ['username' => 'competing', 'email' => 'competing@example.test', 'token' => $conflictToken->token]])), ValidationException::class);
qqCheck(User::count() === $usersBefore && !LoginProvider::where('identifier', 'competing-openid')->exists(), 'A pending registration cannot create a duplicate account after UnionID is claimed');
qqReject(fn() => $db->table('users')->where('id', $fresh->id)->update(['qq_unionid' => 'union-register']), Illuminate\Database\QueryException::class);
qqCheck($fresh->fresh()->qq_unionid === 'union-fresh', 'Database unique index independently prevents duplicate ownership');
$caseUser = qqUser('casesensitive');
$caseUser->qq_unionid = 'UNION-REGISTER'; $caseUser->save();
qqCheck($caseUser->fresh()->qq_unionid !== $registered->qq_unionid, 'Opaque UnionIDs are compared case-sensitively');
$unlinkRequest = RequestUtil::withActor(new Laminas\Diactoros\ServerRequest(), $legacy->fresh());
qqCheck((new QQUnlinkController())->handle($unlinkRequest)->getStatusCode() === 200, 'QQ unlink endpoint succeeds');
qqCheck($legacy->fresh()->qq_unionid === null && !$legacy->loginProviders()->whereIn('provider', QQIdentity::PROVIDERS)->exists(), 'Unlink removes all QQ OpenIDs and the UnionID fallback together');
qqCheck($legacy->loginProviders()->where('provider', 'github')->exists(), 'Unlink preserves other login providers');
qqCheck(QQIdentity::logIn('old-openid', 'union-legacy') === null && QQIdentity::logIn('new-app-openid', null) === null, 'Unlinked identities cannot continue logging in');
qqCheck(!QQIdentity::unlink($legacy->fresh()), 'Repeated unlink returns no association');
$migration['down']($schema);
qqCheck($schema->hasColumn('users', 'qq_unionid') && $registered->fresh()->qq_unionid === 'union-register', 'Uninstall migration preserves UnionID associations and all OpenIDs');
foreach (['en', 'zh', 'zh-Hans'] as $locale) {
    $translator->setLocale($locale);
    qqCheck(!str_contains($translator->trans('hamcq-auth-qq.forum.alerts.identity_conflict'), 'hamcq-auth-qq.'), 'Localized conflict message for '.$locale);
}
echo "PASS: $checks QQ OAuth, legacy login, registration, ownership, unlink and migration checks.\n";
