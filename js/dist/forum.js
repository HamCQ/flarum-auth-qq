module.exports=function(t){var a={};function n(e){if(a[e])return a[e].exports;var o=a[e]={i:e,l:!1,exports:{}};return t[e].call(o.exports,o,o.exports,n),o.l=!0,o.exports}return n.m=t,n.c=a,n.d=function(t,a,e){n.o(t,a)||Object.defineProperty(t,a,{enumerable:!0,get:e})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,a){if(1&a&&(t=n(t)),8&a)return t;if(4&a&&"object"==typeof t&&t&&t.__esModule)return t;var e=Object.create(null);if(n.r(e),Object.defineProperty(e,"default",{enumerable:!0,value:t}),2&a&&"string"!=typeof t)for(var o in t)n.d(e,o,function(a){return t[a]}.bind(null,o));return e},n.n=function(t){var a=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(a,"a",a),a},n.o=function(t,a){return Object.prototype.hasOwnProperty.call(t,a)},n.p="",n(n.s=11)}([function(t,a,n){"use strict";a.a={module:{name:"hamzone-auth-qq",id:"QQAuth",icon:"fab fa-qq"},package:{name:"flarum-ext-auth-qq"},api:{uri:"/auth/qq"}}},function(t,a){t.exports=flarum.core.compat.app},function(t,a,n){"use strict";function e(t,a){t.prototype=Object.create(a.prototype),t.prototype.constructor=t,t.__proto__=a}n.d(a,"a",(function(){return e}))},function(t,a){t.exports=flarum.core.compat["components/Button"]},function(t,a){t.exports=flarum.core.compat["components/Modal"]},function(t,a){t.exports=flarum.core.compat.extend},function(t,a){t.exports=flarum.core.compat["components/SettingsPage"]},function(t,a){t.exports=flarum.core.compat["components/LogInButtons"]},function(t,a){t.exports=flarum.core.compat["components/LogInButton"]},,,function(t,a,n){"use strict";n.r(a);var e=n(5),o=n(1),r=n.n(o),u=n(0),s=n(6),i=n.n(s),l=function(){function t(){}return t.prototype.linkDone=function(t){var a;switch(t){case"already_linked":app.modal.close(),a=app.alerts.show({type:"error"},app.translator.trans(u.a.module.name+".forum.alerts.already_linked"));break;case"already_used":a=app.alerts.show({type:"error"},app.translator.trans(u.a.module.name+".forum.alerts.already_used"));break;case"done":app.modal.close(),app.session.user.savePreferences(),a=app.alerts.show({type:"success"},app.translator.trans(u.a.module.name+".forum.alerts.link_success"));break;case"error":a=app.alerts.show({type:"error"},app.translator.trans(u.a.module.name+".forum.alerts.error"))}setTimeout((function(){app.alerts.dismiss(a)}),5e3)},t}(),c=n(2),p=n(4),d=n.n(p),f=n(3),h=n.n(f),b=function(t){function a(){return t.apply(this,arguments)||this}Object(c.a)(a,t);var n=a.prototype;return n.className=function(){return u.a.module.id+"UnlinkModal Modal--small"},n.title=function(){return app.translator.trans(u.a.module.name+".forum.modals.unlink.title")},n.content=function(){var t=this,a=app.session.user.data.attributes.QQAuth.providersCount,n=void 0===a?0:a;return m("div",{className:"Modal-body"},m("div",{className:"Form Form--centered"},m("div",{className:"Form-group",id:"submit-button-group"},m("h3",null,app.translator.trans(u.a.module.name+".forum.modals.unlink.info.confirm")),n<=1?m("p",{className:u.a.module.id+"Text--danger"},m("i",{className:"fas fa-exclamation-triangle fa-fw"}),m("b",null,app.translator.trans(u.a.module.name+".forum.modals.unlink.info.no_providers"))):"",m("br",null),m("div",{className:"ButtonGroup"},m(h.a,{type:"submit",className:"Button "+u.a.module.id+"Button--danger",icon:"fas fa-exclamation-triangle",loading:this.loading},app.translator.trans(u.a.module.name+".forum.modals.unlink.buttons.confirm")),m(h.a,{className:"Button Button--primary",icon:"fas fa-exclamation-triangle",onclick:function(){return t.hide()},disabled:this.loading},app.translator.trans(u.a.module.name+".forum.modals.unlink.buttons.cancel"))))))},n.onsubmit=function(t){var a,n=this;t.preventDefault(),this.loading=!0,app.request({method:"POST",url:app.forum.attribute("apiUrl")+"/"+u.a.api.uri+"/unlink"}).then((function(){app.session.user.savePreferences(),n.hide(),a=app.alerts.show({type:"success"},app.translator.trans(u.a.module.name+".forum.alerts.unlink_success"))})),setTimeout((function(){app.alerts.dismiss(a)}),5e3)},a}(d.a),g=function(t){function a(){return t.apply(this,arguments)||this}Object(c.a)(a,t);var n=a.prototype;return n.className=function(){return u.a.module.id+"LinkModal Modal--small"},n.title=function(){return app.translator.trans(u.a.module.name+".forum.modals.link.title")},n.content=function(){var t=this;return m("div",{className:"Modal-body"},m("div",{className:"Form Form--centered"},m("div",{className:"Form-group"},m(h.a,{className:"Button LogInButton--"+u.a.module.id,icon:u.a.module.icon,loading:this.loading,disabled:this.loading,path:"/auth/"+name,onclick:function(){return t.showLogin()}},app.translator.trans(u.a.module.name+".forum.buttons.login")))))},n.showLogin=function(){var t=$(window);window.open(app.forum.attribute("apiUrl")+"/"+u.a.api.uri+"/link",u.a.module.id+"LinkPopup","width=600,height=400,top="+(t.height()/2-200)+",left="+(t.width()/2-300)+",status=no,scrollbars=no,resizable=no"),this.loading=!0},a}(d.a),y=n(7),v=n.n(y),k=n(8),x=n.n(k);r.a.initializers.add("nomiscz/"+u.a.package.name,(function(){Object(e.extend)(i.a.prototype,"accountItems",(function(t){var a=r.a.session.user.data.attributes.WeChatAuth.isLinked,n=void 0!==a&&a;t.add("link"+u.a.module.id,m(h.a,{className:"Button "+u.a.module.id+"Button--"+(n?"danger":"success"),icon:u.a.module.icon,path:"/auth/"+name,onclick:function(){return r.a.modal.show(n?b:g)}},r.a.translator.trans(u.a.module.name+".forum.buttons."+(n?"unlink":"link"))))})),Object(e.extend)(v.a.prototype,"items",(function(t){t.add(u.a.package.id,m(x.a,{className:"Button LogInButton--"+u.a.module.id,icon:u.a.module.icon,path:u.a.api.uri},r.a.translator.trans(u.a.module.name+".forum.buttons.login")))}))})),r.a.wechat=new l}]);
//# sourceMappingURL=forum.js.map