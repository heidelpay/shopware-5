//
// {namespace name="backend/config/view/document"}
// {block name="backend/config/view/form/document"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Config.view.form.DocumentHeidelPayment', {
    override: 'Shopware.apps.Config.view.form.Document',
    alias: 'widget.config-form-document-heidel-payment',

    initComponent: function() {
        var me = this;
        me.callParent(arguments);
    },

    /**
     * Overrides the getFormItems method and appends the heidel payment form item
     * @return { Array }
     */
    getFormItems: function() {
        var formItems = this.callParent(arguments);

        var elementFieldSetIndex = -1;
        formItems.forEach(function(item, index) {
            if (item && item.name === 'elementFieldSet') {
                elementFieldSetIndex = index;

                return false;
            }
        });

        if (elementFieldSetIndex === -1) {
            return formItems;
        }

        formItems[elementFieldSetIndex].items.push({
            xtype: 'tinymce',
            fieldLabel: '{s name=heidelpay/info_label_content}{/s}',
            labelWidth: 100,
            name: 'HeidelPayment_Info_Value',
            hidden: true,
            translatable: true
        }, {
            xtype: 'textarea',
            fieldLabel: '{s name=heidelpay/info_label_style}{/s}',
            labelWidth: 100,
            name: 'HeidelPayment_Info_Style',
            hidden: true,
            translatable: true
        }, {
            xtype: 'tinymce',
            fieldLabel: '{s name=heidelpay/footer_label_content}{/s}',
            labelWidth: 100,
            name: 'HeidelPayment_Footer_Value',
            hidden: true,
            translatable: true
        }, {
            xtype: 'textarea',
            fieldLabel: '{s name=heidelpay/footer_label_style}{/s}',
            labelWidth: 100,
            name: 'HeidelPayment_Footer_Style',
            hidden: true,
            translatable: true
        });

        return formItems;
    }
});
// {/block}
