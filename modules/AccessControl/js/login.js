Ext.ns('Oce.Modules.AccessControl');
Oce.Modules.AccessControl.login = Ext.extend(Oce.Module, {

	namespace: 'Oce.Modules'
	,controller: 'AccessControl'
	,name: 'login'

	,constructor: function(config) {
		Oce.Modules.AccessControl.login.superclass.constructor.call(this, config);

		this.addEvents(
			'login'
		);
	}

	,createLoginWindow: function(modal, text) {
		var formPanel = new Ext4.form.Panel({
			border: false
			// @merlin le padding devrait être laissé géré par le CSS en fait
			,padding: 10
			,defaults: {
				validateOnChange: false
			}
			,items: [{
				xtype: 'box'
				,cls: 'alert-login'
				,html: (text ? text : "<?php _jsString($text) ?>")
			},{
				xtype: 'container'
				,items: [	
					{
						xtype: 'textfield'
						,itemId: 'loginField'
						,name: 'username'
						,fieldLabel: "Identifiant" // i18n
						,allowBlank: false
						,minLength: 3
						,maxLength: 45
						,listeners: {
							specialkey: {
								scope: this
								,fn: function(field, el) {
									if (el.getKey() == Ext.EventObject.ENTER) {
										this.onSubmitForm(loginWindow, formPanel);
									}
								}
							}
						}
					},{
						xtype: 'textfield'
						,name: 'password'
						,fieldLabel: "Mot de passe" // i18n
						,inputType: 'password'
						,allowBlank: false
						,minLength: 4
						,maxLength: 255
						,listeners: {
							specialkey: {
								scope: this
								,fn: function(field, el) {
									if (el.getKey() == Ext.EventObject.ENTER) {
										this.onSubmitForm(loginWindow, formPanel);
									}
								}
							}
						}
					}
				]
			}, // @merlin trailing comma in array = null element :(
			]
		});
		
		var loginWindow = new Ext4.Window({
			
			title: "Connexion" // i18n
			
			,cls : 'eoLogin'
			,baseCls : 'eoWindows'
			
			,defaultFocus: 'loginField'
			,width: 380
			
			,modal: modal
			
			,closable: false
			,maximizable: false
			,minimizable: false
			,collapsible: false
			,draggable: true
			,resizable: false
			
			,items: formPanel
			
			,buttons: [
				{ // Reset
						text: "Réinitialiser" // i18n
						,handler: function() {
							formPanel.getForm().reset();
							formPanel.getComponent('loginField').focus();
						}
				},{ // Ok
					text: 'Ok'
					,scope: this
					,cls : 'primary'
					,handler: function() {
						this.onSubmitForm(loginWindow, formPanel);
					}
				}
/*<?php if ($help): ?>*/
				,{ // Help
					iconCls: 'ico_help'
					,handler: this.showHelp
				}
/*<?php endif ?>*/
			]
		});

		return loginWindow;
	}
	
	// private
	,onSubmitForm: function(loginWindow, formPanel) {

		var form = formPanel.getForm();

		if (form.isValid()) {

			// i18n
			loginWindow.el.mask("Interrogation du serveur", 'x-mask-loading');

			eo.Ajax.request({

				params: {
					controller: 'AccessControl.login'
					,action: 'login'
				}

				,jsonData: {
					'username': form.findField('username').getValue()
					,'password': form.findField('password').getValue()
				}

				,scope: this
				,callback: function(options, success, data) {
					var maskEl = loginWindow.el;
					if (maskEl) {
						maskEl.unmask();
					}
					if (success) {
						// Success
						if (data.success) {
							loginWindow.close();
							this.fireEvent('login', data.loginInfos);
						}
						
						// Failure
						else {
							Ext.Msg.alert(
								data.title || "Erreur" // i18n
								,data.errorMessage || data.message || data.msg 
									|| "L'identification a échoué." // i18n
								,function() {
									loginWindow.defaultButton.focus();
								}
							);
						}
					} else {
						// TODO handle error
						debugger
					}
				}

			});

		} else {
			Ext.MessageBox.alert(
				"Erreur", // i18n
				"Les informations que vous avez fourni ne sont pas corrects." // i18n
			);
		}
	}

	,showHelp: function() {

		var win = new eoko.ext.IFrameWindow({
			title: "Aide: Login" // i18n
			,titlePrefix: "Aide&nbsp;: " // i18n
			,width: 400
			,height: 300
			,collapsible: true
			,url: 'http://wiki.eoko-lab.fr'
			,whiteList: /[^.]\.eoko(?:-lab)?\.fr$/
			,panel: {
				showButtonsText: false
				,loadingText: "Chargement..." // i18n
				,proxy: 'proxy.php?url='
				,prepareUrlForProxyRequest: function(uri) {
					uri.queryKey.min = 1;
				}
			}
			,history: {
				back: {iconCls: 'fugico_arrow-180', text: ""}
				,forward: {iconCls: 'fugico_arrow', text: ""}
				,reload: {iconCls: 'arrow-circle-315', text: ""}
			}
			,baseUrl: 'http://wiki.eoko-lab.fr'
		});
		win.show();
	}

	,start: function(modal, text) {
		Ext.getBody().addClass('bg');
		this.showLoginWindow(modal, text);
	}
	
	,showLoginWindow: function(modal, text) {
		this.createLoginWindow(modal, text).show();
	}

});


Oce.Modules.AccessControl.login = new Oce.Modules.AccessControl.login();

Oce.deps.reg('Oce.Modules.AccessControl.login');