Ext.ns('Oce');
Ext.ns('eo.window');

// ----<<  FormWindow  >>-------------------------------------------------------

eo.Window = Ext.extend(Ext.Window, {

	constructor: function(config) {

		var me = this;

		// clone the config object, and applies defaults
		config = Ext.apply({
			constrainHeader: true
			,bodyStyle: 'padding:0'
		}, config);

//		config.minimizable = true;
		config.minimizable = config.minimizable !== false;

		this.addPlugins(config);

		if (Ext.isChrome) {
			Ext.applyIf(config, {
				autoWidth: true
			});
		}

		eo.Window.superclass.constructor.call(this, config);

		if (Ext.isChrome) {
			this.on('afterrender', function() {
				me.setWidth(me.getWidth());
			})
		}
//		eo.Window.superclass.constructor.call(this, config);

		if (this.modalTo) {
			this.setModalTo(this.modalTo);
		}
	}
	
	,deactivateContent: function() {
		Ext.each(this.buttons, function(bt) {
			if (!bt.disabled) {
				bt.disable();
				bt.wasEnabled = true;
			}
		});
		// cannot use toolbar, 'cause the button container is a toolbar and it
		// is quite ugly when masked ...
		if (this.tbar) this.tbar.mask();
		if (this.bbar) this.bbar.mask();
	}
	
	,activateContent: function() {
		Ext.each(this.buttons, function(bt) {
			if (bt.wasEnabled) {
				bt.enable();
				delete bt.wasEnabled;
			}
		});
		if (this.tbar) this.tbar.unmask();
		if (this.bbar) this.bbar.unmask();
	}

	,setModalTo: function(rootWin) {

		var maskEl;
		var win = this;
//		var WIN_CLASS = Oce.FormWindow;
		var WIN_CLASS = Ext.Window;

		var uber = eo.Window.superclass,
			doHide = function() {uber.hide.apply(win, arguments)},
			doShow = function() {uber.show.apply(win, arguments)};

		if (rootWin) {
			maskEl = rootWin && rootWin.items.first();
			if (maskEl) maskEl = maskEl.el;

			this.mon(rootWin, {
				scope: this
				,beforehide: function() {
					var v = !hidding;
					hidding = true;
					doHide();
					modal = v;
				}
				,show: function() {
					if (modal) this.show();
				}
			});
		}

		var hidding = false // this one's to prevent activate event from being process on win hide
		,modal = false // used to control that when the rootWin is restored, the win
				//is displayed only if it was visible when the rootWin was hidden
		,onRootActivateFn = function() {

			if (hidding) return;

			doShow();

			var el = win.el,
				x = el.getX(),
				y = el.getY(),
				h = 8, v = 6, d = .085;
				
			if (!el) return;

			el.stopFx();

			// OptionFX
			var next = function() {return el && !hidding;};
			
			el.moveTo(x, y-v, {
				duration: d
				,callback: function() {
					if (next()) el.moveTo(x,y+v/4, {
						duration: d
						,easing: "easeOut"
						,callback: function() {
							if (next()) el.moveTo(x, y-v*.75, {
								duration: d
								,easing: "easeOut"
								,callback: function() {
									if (next()) el.moveTo(x, y, {
										duration: d
										,callback: function() {
											if (next()) win.focus();
										}
									})
								}
							})
						}
					})
				}
			});

			win.el.frame(null, null, {
				duration: 3*d
			});
		}
		,onRootActivate = function() {
			onRootActivateFn.defer(50);
		};

		this.doShow = function() {

			modal = true;

			if (this.isVisible()) {
				doShow();
				return;
			}

			if (this.clearFormOnShow) {
				var bf = this.form;
				bf.clearInvalid();
				doShow();
				bf.reset();
			} else {
				doShow();
			}

			if (rootWin) {
				// win content
				if (maskEl) maskEl.mask();
				
				if (rootWin.deactivateContent) {
					rootWin.deactivateContent();
				} else {
					rootWin.disable();
				}
				rootWin.on('activate', onRootActivate);
			}
			hidding = false;
		}

		this.doHide = function() {

			modal = true;
			hidding = true;

			this.el.stopFx();

			if (rootWin) {
				// unmask win content
				if (maskEl) maskEl.unmask();
				
				if (rootWin.activateContent) {
					rootWin.activateContent();
				} else {
					rootWin.enable();
				}
				rootWin.un('activate', onRootActivate);
				doHide.apply(this, arguments);
				//maskEl.unmask();
				//rootWin.enable();
			}
		}
	}

	,doHide: function() {
		eo.Window.superclass.hide.apply(this, arguments);
	}

	,doShow: function() {
		eo.Window.superclass.show.apply(this, arguments);
	}
	
	,hide: function() {
		this.doHide.apply(this, arguments);
	}
	
	,show: function() {
		this.doShow();
	}

	,addPlugins: function(config) {
		if (!config.plugins) config.plugins = [];
		if (config.minimizable !== false) {
			config.plugins.push(new eo.Window.MinimizePlugin);
		}
	}
});

Oce.deps.reg('eo.Window');

Oce.FormWindow = Ext.extend(eo.Window, {

	formRefreshers: []
	,formPages: null // wait events
	,pkName: 'id'

	,constructor: function(config) {

		var me = this;

		// --- Items default ---
		var formPanel;
		if (config.formPanel) {
			if (config.formPanel instanceof Ext.Component) {
				formPanel = config.formPanel;
				if (formPanel.initialConfig.autoScroll === undefined) {
					formPanel.setAutoScroll(true);
				}
			} else {
//				Ext.applyIf(config.formPanel, {
//					autoScroll: true
//				});
//				formPanel = Ext.create(config.formPanel);
				formPanel = Ext.create(
					Ext.applyIf(config.formPanel, {
						autoScroll: true
					})
				);
			}

			config.items = [formPanel];
		}

		// --- Reload tool ---
		// Refresh gear button

		// Implements the window refresh tool button, if not already set in the
		// config (in this case, we must find by its id to know...)
// Hardly ever useful... Keep some time to be sure it was not used, however
//		if (config.tools !== undefined) {
//			var ids = Ext.pluck(config.tool, 'id');
//			var free = true;
//			for (var i=0,l=ids; i<l; i++) {
//				if (ids[i] === 'refresh') {
//					free = false;
//					break;
//				}
//			}
//			if (free) config.tools.push({
//				id: 'refresh'
//				,handler: this.refresh.createDelegate(this)
//			});
//		} else {
//			config.tools = [];
//			config.tools = [{
//				id: 'refresh'
//				,handler: this.refresh.createDelegate(this)
//			}];
//		}
		config.tools = config.tools || [];
		if (config.refreshable) {
			config.tools.push({
				id: 'refresh'
				,handler: this.refresh.createDelegate(this)
			});
		}

		// --- Default config ---

		Ext.applyIf(config, {
			 closable: true
			,collapsible: false
			,loadMask: true
//			,constrainHeader: true
//			,bodyStyle: 'padding:0'
			,maximizable: true
			,layout:'fit'
//			,closeAction: 'hide'
		})


		// --- Fix crazy window auto width for Chrome ---

//		if (Ext.isChrome) {
//			Ext.applyIf(config, {
//				autoWidth: true
//			});
//		}
//
		Oce.FormWindow.superclass.constructor.call(this, config)
//
//		if (Ext.isChrome) {
//			this.on('afterrender', function() {
//				me.setWidth(me.getWidth());
//			})
//		}

		if (formPanel.setWindow) formPanel.setWindow(this);
		
		// --- Shortcut accessors ---

		this.formPanel = formPanel;
		this.form = this.formPanel.form;
	}
	
	,afterRender: function() {
		Oce.FormWindow.superclass.afterRender.apply(this, arguments);
		
		var sb = this.getSubmitButton();
		if (sb) {
			sb.addClass("default");
		}
	}
	
	,initEvents: function() {
		Oce.FormWindow.superclass.initEvents.call(this);
		
		// Handle default (submit) button on ENTER key press
		var sh, scope;
		if (this.submitHandler) {
			sh = this.submitHandler;
			scope = this.scope || this;
		} else {
			var sb = this.getSubmitButton();
			if (sb) {
				sh = sb.handler;
				scope = sb.scope || sb;
			}
		}
		
		if (sh) {
			var km = this.getKeyMap();
			km.on(13, sh, scope);
			km.disable();
		}
	}
	
	,getSubmitButton: function() {
		var sb = this.submitButton;
        if(Ext.isDefined(sb)){
            if(Ext.isNumber(sb) && this.fbar){
                return this.fbar.items.get(sb);
            }else if(Ext.isString(sb)){
                return Ext.getCmp(sb);
            }else{
                return sb;
            }
        }
		return null;
	}

	,disable: function() {
		var oldWidth = this.getWidth();
		this.setWidth(oldWidth);
		Oce.FormWindow.superclass.disable.apply(this, arguments);
	}

	,getForm: function() {return this.form;}
	,getFormPanel: function() {return this.formPanel;}
	,getRowId: function() {return this.idValue;}

	/**
	 * @param {boolean} force if set to TRUE, the beforerefresh event won't be
	 * fired, so external observers won't be given the opportunity to cancel the
	 * refreshing.
	 */
	,refresh: function(force) {
		force = force === true;
		var me = this;
		var doRefresh = function() {
			me.items.each(function(item) {
				if (Ext.isFunction(item.refresh)) {
					item.refresh();
				}
			});
		}
		if (force || this.fireEvent('beforerefresh', this, doRefresh) !== false) {
			doRefresh();
		}
	}

	,setRow: function(row) {

		var rowId = this.rowId = this.idValue
			= row.data[this.pkName];

		this.form.items.each(function(item) {
			if (item instanceof Oce.form.ForeignComboBox
				|| item instanceof Oce.form.GridField) {

				if (item.idField) {
					item.setRowId(row.data[item.idField], true);
				} else {
					item.setRowId(rowId, true);
				}
			}
		})
	}

	,setRowId: function(rowId) {
		this.rowId = this.idValue = rowId;
		this.formPanel.initFormItems = this.setRow.createDelegate(this);
	}

});

eo.Window.MinimizePlugin = eo.Object.create({

	init: function(win) {
		var taskbar = Oce.mx.TaskBar;
		if (!taskbar) return;

		Ext.each(win.tools, function(tool) {
			if (tool.id === "minimize") {
				tool.handler = function() {
					win.minimize()
				};
			}
		});

		win.afterRender = win.afterRender.createSequence(function() {
			taskbar.addWindow(this);
		});
	}
});