module.exports=function(t){var e={};function n(r){if(e[r])return e[r].exports;var o=e[r]={i:r,l:!1,exports:{}};return t[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}return n.m=t,n.c=e,n.d=function(t,e,r){n.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:r})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,e){if(1&e&&(t=n(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var o in t)n.d(r,o,function(e){return t[e]}.bind(null,o));return r},n.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(e,"a",e),e},n.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},n.p="",n(n.s=5)}({4:function(t,e){t.exports=flarum.core.compat["components/SettingsModal"]},5:function(t,e,n){"use strict";n.r(e);var r=n(4),o=function(t){var e,n;function r(){return t.apply(this,arguments)||this}n=t,(e=r).prototype=Object.create(n.prototype),e.prototype.constructor=e,e.__proto__=n;var o=r.prototype;return o.className=function(){return"QQSettingsModal Modal--small"},o.title=function(){return app.translator.trans("hehongyuanlove-auth-qq.admin.qq_settings.title")},o.form=function(){return[m("div",{className:"Form-group"},m("label",null,app.translator.trans("hehongyuanlove-auth-qq.admin.qq_settings.client_id_label")),m("input",{className:"FormControl",bidi:this.setting("hehongyuanlove-auth-qq.client_id")})),m("div",{className:"Form-group"},m("label",null,app.translator.trans("hehongyuanlove-auth-qq.admin.qq_settings.client_secret_label")),m("input",{className:"FormControl",bidi:this.setting("hehongyuanlove-auth-qq.client_secret")}))]},r}(n.n(r).a);app.initializers.add("hehongyuanlove-auth-qq",(function(){app.extensionSettings["hehongyuanlove-auth-qq"]=function(){return app.modal.show(new o)}}))}});
//# sourceMappingURL=admin.js.map