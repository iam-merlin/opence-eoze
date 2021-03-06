Oce.deps.wait('Oce.GridModule', function() {

	Oce.GridModule.ContentKit = Ext.extend(Ext.util.Observable, {

		component: null

		,EVENT_FORM_PANEL_CREATE: 'formpanelcreate'

		,constructor: function(config) {
			
			this.addEvents({
				formpanelcreate: true
			});
			
			Oce.GridModule.ContentKit.superclass.constructor.call(this, config);
			Ext.apply(this, config);
		}

		,createWindow: function() {

			var config = Ext.apply({
				pkName: this.pkName
				,kit: this
				,formPanel: this.content
			}, this.winConfig);

			var cls = config.xclass || 'Eoze.GridModule.form.EditWindow';

			var win = Ext.create(cls, config);

			this.fireEvent(this.EVENT_FORM_PANEL_CREATE, win.formPanel);

			return win;
		}

		,getWin: function() {
			if (this.component !== null) {
				if (this.component instanceof Ext.Window) return this.component;
				else throw new Error("Content already rendered in panel")
			} else {
				return this.component = this.createWindow();
			}
		}

	});

});

Oce.FormWindowPanel = Ext.extend(Ext.Panel, {
	
	EVENT_CLOSE: 'close'

	,initComponent: function() {
		
		this.formRefreshers = [];
		
		// Layout
		Ext.apply(this, {
			layout: 'fit'
			,closable: true
			,bodyStyle: {
				backgroundColor: '#eee'
			}
		});
		
		// Remove tools
		// TODO FormWindowPanel tools
		delete this.tools;

		// Create FormPanel as needed
		var fp = this.formPanel;
		if (fp) {
			if (fp instanceof Ext.Component) {
				this.formPanel = fp;
				if (this.formPanel.initialConfig.autoScroll === undefined) {
					this.formPanel.setAutoScroll(true);
				}
			} else {
				this.formPanel = Ext.widget(
					Ext.applyIf({
						autoScroll: true
					}, fp)
				);
			}
			
			this.items = [this.formPanel];
			
		} else {
			throw new Error('formPanel config is required.');
		}
		
		this.form = this.formPanel.getForm();
		
		// FormPanel setWindow
		this.formPanel.setWindow(this);
		
		this.callParent();
	}
	
	,getForm: function() {
		return this.form;
	}

	,close: function() {
		this.fireEvent(this.EVENT_CLOSE, this);
		this.destroy(); // there is no risk at destroying multiple times
	}

	,setRow: function(row) {

		var rowId = this.rowId = this.idValue
			= row.data[this.pkName];

		this.getForm().items.each(function(item) {
			if (item instanceof Oce.form.ForeignComboBox
				|| item instanceof Oce.form.GridField) {

				if (item.idField) {
					item.setRowId(row.data[item.idField], true);
				} else {
					item.setRowId(rowId, true);
				}
			}
		});
	}

	/**
	 * Used by GridModule save methods.
	 */
	,mask: function(text, cls) {
		this.el.mask(text, cls);
	}
	/**
	 * Used by GridModule save methods.
	 */
	,unmask: function() {
		this.el.unmask();
	}
});
