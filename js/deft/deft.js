(function(Ext) {

/*
DeftJS 0.6.7

Copyright (c) 2012 [DeftJS Framework Contributors](http://deftjs.org)
Open source under the [MIT License](http://en.wikipedia.org/wiki/MIT_License).
*/
Ext.define("Deft.log.Logger",{alternateClassName:["Deft.Logger"],singleton:!0,log:function(){},error:function(a){this.log(a,"error")},info:function(a){this.log(a,"info")},verbose:function(a){this.log(a,"verbose")},warn:function(a){this.log(a,"warn")},deprecate:function(a){this.log(a,"deprecate")}},function(){var a;if(Ext.isFunction((a=Ext.Logger)!=null?a.log:void 0))this.log=Ext.bind(Ext.Logger.log,Ext.Logger);else if(Ext.isFunction(Ext.log))this.log=function(a,b){b==null&&(b="info");b==="deprecate"&&
(b="warn");Ext.log({msg:a,level:b})}});Ext.define("Deft.util.Function",{alternateClassName:["Deft.Function"],statics:{spread:function(a,c){return function(b){Ext.isArray(b)||Ext.Error.raise({msg:"Error spreading passed Array over target function arguments: passed a non-Array."});return a.apply(c,b)}},memoize:function(a,c,b){var d;d={};return function(e){var g;g=Ext.isFunction(b)?b.apply(c,arguments):e;g in d||(d[g]=a.apply(c,arguments));return d[g]}}}});
Ext.define("Deft.ioc.DependencyProvider",{requires:["Deft.log.Logger"],config:{identifier:null,className:null,parameters:null,fn:null,value:null,singleton:!0,eager:!1},constructor:function(a){this.initConfig(a);a.value!=null&&a.value.constructor===Object&&this.setValue(a.value);this.getEager()&&(this.getValue()!=null&&Ext.Error.raise({msg:"Error while configuring '"+this.getIdentifier()+"': a 'value' cannot be created eagerly."}),this.getSingleton()||Ext.Error.raise({msg:"Error while configuring '"+
this.getIdentifier()+"': only singletons can be created eagerly."}));this.getClassName()!=null&&(a=Ext.ClassManager.get(this.getClassName()),a==null&&(Deft.Logger.warn("Synchronously loading '"+this.getClassName()+"'; consider adding Ext.require('"+this.getClassName()+"') above Ext.onReady."),Ext.syncRequire(this.getClassName()),a=Ext.ClassManager.get(this.getClassName())),a==null&&Ext.Error.raise({msg:"Error while configuring rule for '"+this.getIdentifier()+"': unrecognized class name or alias: '"+
this.getClassName()+"'"}));this.getSingleton()?this.getClassName()!=null&&this.getParameters()!=null&&Ext.ClassManager.get(this.getClassName()).singleton&&Ext.Error.raise({msg:"Error while configuring rule for '"+this.getIdentifier()+"': parameters cannot be applied to singleton classes. Consider removing 'singleton: true' from the class definition."}):(this.getClassName()!=null&&Ext.ClassManager.get(this.getClassName()).singleton&&Ext.Error.raise({msg:"Error while configuring rule for '"+this.getIdentifier()+
"': singleton classes cannot be configured for injection as a prototype. Consider removing 'singleton: true' from the class definition."}),this.getValue()!=null&&Ext.Error.raise({msg:"Error while configuring '"+this.getIdentifier()+"': a 'value' can only be configured as a singleton."}));return this},resolve:function(a){var c;Deft.Logger.log("Resolving '"+this.getIdentifier()+"'.");if(this.getValue()!=null)return this.getValue();c=null;this.getFn()!=null?(Deft.Logger.log("Executing factory function."),
c=this.getFn().call(null,a)):this.getClassName()!=null?Ext.ClassManager.get(this.getClassName()).singleton?(Deft.Logger.log("Using existing singleton instance of '"+this.getClassName()+"'."),c=Ext.ClassManager.get(this.getClassName())):(Deft.Logger.log("Creating instance of '"+this.getClassName()+"'."),a=this.getParameters()!=null?[this.getClassName()].concat(this.getParameters()):[this.getClassName()],c=Ext.create.apply(this,a)):Ext.Error.raise({msg:"Error while configuring rule for '"+this.getIdentifier()+
"': no 'value', 'fn', or 'className' was specified."});this.getSingleton()&&this.setValue(c);return c}});
Ext.define("Deft.ioc.Injector",{alternateClassName:["Deft.Injector"],requires:["Deft.log.Logger","Deft.ioc.DependencyProvider"],singleton:!0,constructor:function(){this.providers={};return this},configure:function(a){Deft.Logger.log("Configuring injector.");Ext.Object.each(a,function(a,b){var d;Deft.Logger.log("Configuring dependency provider for '"+a+"'.");d=Ext.isString(b)?Ext.create("Deft.ioc.DependencyProvider",{identifier:a,className:b}):Ext.create("Deft.ioc.DependencyProvider",Ext.apply({identifier:a},
b));this.providers[a]=d},this);Ext.Object.each(this.providers,function(a,b){b.getEager()&&(Deft.Logger.log("Eagerly creating '"+b.getIdentifier()+"'."),b.resolve())},this)},canResolve:function(a){return this.providers[a]!=null},resolve:function(a,c){var b;b=this.providers[a];if(b!=null)return b.resolve(c);else Ext.Error.raise({msg:"Error while resolving value to inject: no dependency provider found for '"+a+"'."})},inject:function(a,c,b){var d,e,g,f;b==null&&(b=!0);d={};Ext.isString(a)&&(a=[a]);Ext.Object.each(a,
function(b,e){var g,f;f=Ext.isArray(a)?e:b;g=this.resolve(e,c);f in c.config?(Deft.Logger.log("Injecting '"+e+"' into '"+f+"' config."),d[f]=g):(Deft.Logger.log("Injecting '"+e+"' into '"+f+"' property."),c[f]=g)},this);if(b)for(e in d)f=d[e],b="set"+Ext.String.capitalize(e),c[b].call(c,f);else if(Ext.isFunction(c.initConfig))g=c.initConfig,c.initConfig=function(a){return g.call(this,Ext.Object.merge({},a||{},d))};return c}});
Ext.define("Deft.mixin.Injectable",{requires:["Deft.ioc.Injector"],onClassMixedIn:function(a){a.prototype.constructor=Ext.Function.createInterceptor(a.prototype.constructor,function(){return Deft.Injector.inject(this.inject,this,!1)})}});
Ext.define("Deft.mvc.ViewController",{alternateClassName:["Deft.ViewController"],requires:["Deft.log.Logger"],config:{view:null},constructor:function(a){this.initConfig(a);if(this.getView()instanceof Ext.ClassManager.get("Ext.Component"))if(this.registeredComponents={},this.isExtJS=this.getView().events!=null,this.isSenchaTouch=!this.isExtJS,this.isExtJS)if(this.getView().rendered)this.onViewInitialize();else this.getView().on("afterrender",this.onViewInitialize,this,{single:!0});else if(this.getView().initialized)this.onViewInitialize();
else this.getView().on("initialize",this.onViewInitialize,this,{single:!0});else Ext.Error.raise({msg:"Error constructing ViewController: the configured 'view' is not an Ext.Component."});return this},init:function(){},destroy:function(){return!0},onViewInitialize:function(){var a,c,b,d,e,g;this.isExtJS?(this.getView().on("beforedestroy",this.onViewBeforeDestroy,this),this.getView().on("destroy",this.onViewDestroy,this,{single:!0})):(e=this,d=this.getView().destroy,this.getView().destroy=function(){e.destroy()&&
d.call(this)});g=this.control;for(b in g)c=g[b],a=this.locateComponent(b,c),c=Ext.isObject(c.listeners)?c.listeners:c.selector==null?c:void 0,this.registerComponent(b,a,c);this.init()},onViewBeforeDestroy:function(){return this.destroy()?(this.getView().un("beforedestroy",this.onBeforeDestroy,this),!0):!1},onViewDestroy:function(){for(var a in this.registeredComponents)this.unregisterComponent(a)},getComponent:function(a){var c;return(c=this.registeredComponents[a])!=null?c.component:void 0},registerComponent:function(a,
c,b){var d,e,g,f,h;Deft.Logger.log("Registering '"+a+"' component.");this.getComponent(a)!=null&&Ext.Error.raise({msg:"Error registering component: an existing component already registered as '"+a+"'."});this.registeredComponents[a]={component:c,listeners:b};a!=="view"&&(e="get"+Ext.String.capitalize(a),this[e]||(this[e]=Ext.Function.pass(this.getComponent,[a],this)));if(Ext.isObject(b))for(d in b){e=g=b[d];h=this;f=null;if(Ext.isObject(g)){f=Ext.apply({},g);if(f.fn!=null)e=f.fn,delete f.fn;if(f.scope!=
null)h=f.scope,delete f.scope}Deft.Logger.log("Adding '"+d+"' listener to '"+a+"'.");if(Ext.isFunction(e))c.on(d,e,h,f);else if(Ext.isFunction(this[e]))c.on(d,this[e],h,f);else Ext.Error.raise({msg:"Error adding '"+d+"' listener: the specified handler '"+e+"' is not a Function or does not exist."})}},unregisterComponent:function(a){var c,b,d,e,g,f;Deft.Logger.log("Unregistering '"+a+"' component.");this.getComponent(a)==null&&Ext.Error.raise({msg:"Error unregistering component: no component is registered as '"+
a+"'."});d=this.registeredComponents[a];c=d.component;g=d.listeners;if(Ext.isObject(g))for(b in g){d=e=g[b];f=this;if(Ext.isObject(e)){if(e.fn!=null)d=e.fn;if(e.scope!=null)f=e.scope}Deft.Logger.log("Removing '"+b+"' listener from '"+a+"'.");Ext.isFunction(d)?c.un(b,d,f):Ext.isFunction(this[d])?c.un(b,this[d],f):Ext.Error.raise({msg:"Error removing '"+b+"' listener: the specified handler '"+d+"' is not a Function or does not exist."})}a!=="view"&&(c="get"+Ext.String.capitalize(a),this[c]=null);this.registeredComponents[a]=
null},locateComponent:function(a,c){var b;b=this.getView();if(a==="view")return b;Ext.isString(c)?(b=b.query(c),b.length===0&&Ext.Error.raise({msg:"Error locating component: no component found matching '"+c+"'."}),b.length>1&&Ext.Error.raise({msg:"Error locating component: multiple components found matching '"+c+"'."})):Ext.isString(c.selector)?(b=b.query(c.selector),b.length===0&&Ext.Error.raise({msg:"Error locating component: no component found matching '"+c.selector+"'."}),b.length>1&&Ext.Error.raise({msg:"Error locating component: multiple components found matching '"+
c.selector+"'."})):(b=b.query("#"+a),b.length===0&&Ext.Error.raise({msg:"Error locating component: no component found with an itemId of '"+a+"'."}),b.length>1&&Ext.Error.raise({msg:"Error locating component: multiple components found with an itemId of '"+a+"'."}));return b[0]}});Ext.define("Deft.mixin.Controllable",{});
Ext.Class.registerPreprocessor("controller",function(a,c,b,d){var e,g,f;arguments.length===3&&(g=Ext.toArray(arguments),b=g[1],d=g[2]);if(c.mixins!=null&&Ext.Array.contains(c.mixins,Ext.ClassManager.get("Deft.mixin.Controllable"))&&(e=c.controller,delete c.controller,e!=null))return a.prototype.constructor=Ext.Function.createSequence(a.prototype.constructor,function(){var b;try{b=Ext.create(e,{view:this})}catch(c){throw Deft.Logger.warn("Error initializing Controllable instance: an error occurred while creating an instance of the specified controller: '"+
e+"'."),c;}if(this.getController==null)this.getController=function(){return b},a.prototype.destroy=Ext.Function.createSequence(a.prototype.destroy,function(){delete this.getController})}),f=this,Ext.require([e],function(){d!=null&&d.call(f,a,c,b)}),!1});Ext.Class.setDefaultPreprocessorPosition("controller","before","mixins");
Ext.define("Deft.promise.Deferred",{alternateClassName:["Deft.Deferred"],constructor:function(){this.state="pending";this.value=this.progress=void 0;this.progressCallbacks=[];this.successCallbacks=[];this.failureCallbacks=[];this.cancelCallbacks=[];this.promise=Ext.create("Deft.Promise",this);return this},then:function(a,c,b,d){var e,g,f,h,i;Ext.isObject(a)?(g=a.success,c=a.failure,b=a.progress,a=a.cancel):(g=a,a=d);i=[g,c,b,a];f=0;for(h=i.length;f<h;f++)d=i[f],!Ext.isFunction(d)&&!(d===null||d===
void 0)&&Ext.Error.raise({msg:"Error while configuring callback: a non-function specified."});e=Ext.create("Deft.promise.Deferred");d=function(a,b){return function(c){var d;if(Ext.isFunction(a))try{if(d=a(c),d===void 0)e[b](c);else d instanceof Ext.ClassManager.get("Deft.promise.Promise")||d instanceof Ext.ClassManager.get("Deft.promise.Deferred")?d.then(Ext.bind(e.resolve,e),Ext.bind(e.reject,e),Ext.bind(e.update,e),Ext.bind(e.cancel,e)):e.resolve(d)}catch(f){e.reject(f)}else e[b](c)}};this.register(d(g,
"resolve"),this.successCallbacks,"resolved",this.value);this.register(d(c,"reject"),this.failureCallbacks,"rejected",this.value);this.register(d(a,"cancel"),this.cancelCallbacks,"cancelled",this.value);this.register(function(a){return function(b){var c;Ext.isFunction(a)?(c=a(b),c===void 0?e.update(b):e.update(c)):e.update(b)}}(b),this.progressCallbacks,"pending",this.progress);return e.getPromise()},always:function(a){return this.then({success:a,failure:a,cancel:a})},update:function(a){this.state===
"pending"?(this.progress=a,this.notify(this.progressCallbacks,a)):Ext.Error.raise({msg:"Error: this Deferred has already been completed and cannot be modified."})},resolve:function(a){this.complete("resolved",a,this.successCallbacks)},reject:function(a){this.complete("rejected",a,this.failureCallbacks)},cancel:function(a){this.complete("cancelled",a,this.cancelCallbacks)},getPromise:function(){return this.promise},getState:function(){return this.state},register:function(a,c,b,d){Ext.isFunction(a)&&
(this.state==="pending"?(c.push(a),this.state===b&&d!==void 0&&this.notify([a],d)):this.state===b&&this.notify([a],d))},complete:function(a,c,b){this.state==="pending"?(this.state=a,this.value=c,this.notify(b,c),this.releaseCallbacks()):Ext.Error.raise({msg:"Error: this Deferred has already been completed and cannot be modified."})},notify:function(a,c){var b,d,e;d=0;for(e=a.length;d<e;d++)b=a[d],b(c)},releaseCallbacks:function(){this.cancelCallbacks=this.failureCallbacks=this.successCallbacks=this.progressCallbacks=
null}});
Ext.define("Deft.promise.Promise",{alternateClassName:["Deft.Promise"],statics:{when:function(a){var c;return a instanceof Ext.ClassManager.get("Deft.promise.Promise")||a instanceof Ext.ClassManager.get("Deft.promise.Deferred")?a.then():(c=Ext.create("Deft.promise.Deferred"),c.resolve(a),c.then())},all:function(a){return this.when(this.reduce(a,this.reduceIntoArray,Array(a.length)))},any:function(a){var c,b,d,e,g,f,h,i,j,k,l;b=Ext.create("Deft.promise.Deferred");j=function(a){b.update(a)};i=function(a){c();
b.resolve(a)};c=function(){return j=i=function(){}};h=function(a){return i(a)};f=function(a){return rejector(a)};e=function(a){return j(a)};d=k=0;for(l=a.length;k<l;d=++k)g=a[d],d in a&&this.when(g).then(h,f,e);return this.when(b)},memoize:function(a,c,b){return this.all(Ext.Array.toArray(arguments)).then(Deft.util.Function.spread(function(){return Deft.util.memoize(arguments,c,b)},c))},map:function(a,c){var b,d,e,g,f;e=Array(a.length);b=g=0;for(f=a.length;g<f;b=++g)d=a[b],b in a&&(e[b]=this.when(d,
c));return this.reduce(e,this.reduceIntoArray,e)},reduce:function(a,c,b){var d,e;e=this.when;d=[function(b,d,h){return e(b,function(b){return e(d,function(d){return c(b,d,h,a)})})}];arguments.length===3&&d.push(b);return this.when(this.reduceArray.apply(a,d))},reduceArray:function(a,c){var b,d,e,g,f;e=0;d=Object(this);g=d.length>>>0;b=arguments;if(b.length<=1)for(;;){if(e in d){f=d[e++];break}if(++e>=g)throw new TypeError;}else f=b[1];for(;e<g;)e in d&&(f=a(f,d[e],e,d)),e++;return f},reduceIntoArray:function(a,
c,b){a[b]=c;return a}},constructor:function(a){this.deferred=a;return this},then:function(a){return this.deferred.then.apply(this.deferred,arguments)},always:function(a){return this.deferred.always(a)},cancel:function(a){return this.deferred.cancel(a)},getState:function(){return this.deferred.getState()}},function(){if(Array.prototype.reduce!=null)this.reduceArray=Array.prototype.reduce});

})(Ext4);
