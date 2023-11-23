
A [Flarum](http://flarum.org) extension. Allow users to log in with QQ
### 问题
 - 手机登录 要触发两次登录

### QQ互联回调地址

- https://域名/api/auth/qq
- https://域名/api/authh5/qq (没必要加 自用的)

### 安装

Use [Bazaar](https://discuss.flarum.org/d/5151-flagrow-bazaar-the-extension-marketplace) or install manually with composer:

```sh
composer require hamcq/flarum-auth-qq
# 有兼容提示就
composer require hamcq/flarum-auth-qq:*
```

### 更新

```sh
composer update hamcq/flarum-auth-qq
```

