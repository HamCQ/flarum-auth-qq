module.exports=function(t){var e={};function n(o){if(e[o])return e[o].exports;var r=e[o]={i:o,l:!1,exports:{}};return t[o].call(r.exports,r,r.exports,n),r.l=!0,r.exports}return n.m=t,n.c=e,n.d=function(t,e,o){n.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:o})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,e){if(1&e&&(t=n(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var o=Object.create(null);if(n.r(o),Object.defineProperty(o,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var r in t)n.d(o,r,function(e){return t[e]}.bind(null,r));return o},n.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(e,"a",e),e},n.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},n.p="",n(n.s=8)}([function(t,e){t.exports=flarum.core.compat.app},function(t,e,n){"use strict";function o(t,e){t.prototype=Object.create(e.prototype),t.prototype.constructor=t,t.__proto__=e}n.d(e,"a",(function(){return o}))},function(t,e){t.exports=flarum.core.compat.extend},function(t,e){t.exports=flarum.core.compat["components/LogInButtons"]},function(module,__webpack_exports__,__webpack_require__){"use strict";__webpack_require__.d(__webpack_exports__,"a",(function(){return QQLogInButton}));var _babel_runtime_helpers_esm_inheritsLoose__WEBPACK_IMPORTED_MODULE_0__=__webpack_require__(1),flarum_app__WEBPACK_IMPORTED_MODULE_1__=__webpack_require__(0),flarum_app__WEBPACK_IMPORTED_MODULE_1___default=__webpack_require__.n(flarum_app__WEBPACK_IMPORTED_MODULE_1__),flarum_components_Button__WEBPACK_IMPORTED_MODULE_2__=__webpack_require__(5),flarum_components_Button__WEBPACK_IMPORTED_MODULE_2___default=__webpack_require__.n(flarum_components_Button__WEBPACK_IMPORTED_MODULE_2__),QQLogInButton=function(_Button){function QQLogInButton(){return _Button.apply(this,arguments)||this}Object(_babel_runtime_helpers_esm_inheritsLoose__WEBPACK_IMPORTED_MODULE_0__.a)(QQLogInButton,_Button),QQLogInButton.initAttrs=function(t){t.authsQQ=this.authsQQ,_Button.initAttrs.call(this,t)};var _proto=QQLogInButton.prototype;return _proto.view=function(t){var e=_Button.prototype.view.call(this,t);return e.attrs.onclick=this.checkH5.bind(this),e.attrs.className+=" LogInButton",e},_proto.checkH5=function(){console.log(this);var t=$(window);window.open(flarum_app__WEBPACK_IMPORTED_MODULE_1___default.a.forum.attribute("apiUrl")+"/auth/qq","LinkPopup","width=600,height=400,top="+(t.height()/2-200)+",left="+(t.width()/2-300)+",status=no,scrollbars=no,resizable=no"),this.loading=!0},_proto.authLogin=function(){var t=this;this.authsQQ.login((function(e){e.target.authResult;t.authUserInfo()}),(function(t){alert("登录认证失败！")}),{})},_proto.authLogout=function(){for(var t in this.auths){var e=auths[t];e.authResult&&e.logout((function(t){alert("注销登录认证成功！")}),(function(t){alert("注销登录认证失败！")}))}},_proto.authUserInfo=function authUserInfo(){var s=this.authsQQ;s.authResult?s.getUserInfo((function(e){var pload={openid:s.authResult.openid,access_token:s.authResult.access_token,pay_token:s.authResult.pay_token,nickname:s.userInfo.nickname,figureurl_qq_2:s.userInfo.figureurl_qq_2},prame=escape(JSON.stringify(pload));m.request({method:"GET",url:"/api/authh5/qq?param="+prame,deserialize:function(t){return t}}).then((function(result){result=result.replace("window.close();",""),result=result.replace(".opener",""),result=result.replace("<script>",""),result=result.replace(";<\/script>",""),eval(result)})).catch((function(t){console.log(t)}))}),(function(t){alert("获取用户信息失败："+t.message+" - "+t.code)})):alert("未登录授权！")},QQLogInButton}(flarum_components_Button__WEBPACK_IMPORTED_MODULE_2___default.a)},function(t,e){t.exports=flarum.core.compat["components/Button"]},,,function(t,e,n){"use strict";n.r(e);var o=n(2),r=n(0),u=n.n(r),_=n(3),a=n.n(_),i=n(4);u.a.initializers.add("hehongyuanlove-auth-qq",(function(){Object(o.extend)(a.a.prototype,"items",(function(t){t.add("QQAndH5",m(i.a,{className:"Button LogInButton--QQ",icon:"fab fa-qq"},u.a.translator.trans("hehongyuanlove-auth-qq.forum.log_in.with_qq_button")))}))}))}]);
//# sourceMappingURL=forum.js.map