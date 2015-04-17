/**
 Functionality for the taxonomy view page of the "Vendors" taxonomy.

 @since 0.1.0
 */
var WC_Vendor_Modifications;
(function ($) {
    'use strict';

    //noinspection JSUnresolvedVariable
    WC_Vendor_Modifications = {

        elements: {},
        data: WC_Vendor_Modifications_Data,
        initialCommision: 0,

        init: function () {

            this._getElements();
            this._bindHandlers();

            this.initialCommision = parseInt(this.elements['commission_input'].val());
        },

        _getElements: function () {

            this.elements['parent_select'] = $('#parent');
            this.elements['commission_input'] = $('#vendor_commission');
        },

        _bindHandlers: function () {

            this.elements['parent_select'].change(this.parentFieldChange);
        },

        parentFieldChange: function () {

            var _this = WC_Vendor_Modifications,
                depth = $(this).find(':selected').attr('class').match(/level-(\d*)/),
                commission_percentage, new_commission;

            if (!depth[1]) {
                return;
            }

            depth = parseInt(depth[1]);
            commission_percentage = parseInt(_this.data['commission_percentage']);
            new_commission = _this.initialCommision * ((100 - (commission_percentage * depth)) / 100);

            _this.elements['commission_input'].val(new_commission);
        }
    };

    // Init object on taxonomy page
    if ($('body').hasClass('taxonomy-shop_vendor')) {
        WC_Vendor_Modifications.init();
    }

})(jQuery);