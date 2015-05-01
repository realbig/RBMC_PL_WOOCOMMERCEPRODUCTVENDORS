/**
 Functionality for the taxonomy view page of the "Vendors" taxonomy.

 @since 0.1.0
 */
var WC_MLM_Reporting;
(function ($) {
    'use strict';

    //noinspection JSUnresolvedVariable
    WC_MLM_Reporting = {

        $report: null,

        init: function () {

            this.getElements();

            if (!this.$report.length) {
                return;
            }

            this.setupDatePickers();
        },

        getElements: function () {

            this.$report = $('.wc-mlm-report');
        },

        setupDatePickers: function () {

            this.$report.each(function () {

                var $date_from = $(this).find('.vendor-report-period-from'),
                    $date_to = $(this).find('.vendor-report-period-to'),
                    $report = $(this);

                $date_from.datepicker({
                    defaultDate: "-1m",
                    altField: $date_from.siblings('[name="' + $date_from.attr('class') + '"]'),
                    altFormat: 'mm_dd_yy',
                    changeMonth: true,
                    numberOfMonths: 1,
                    onClose: function( selectedDate ) {
                        $date_to.datepicker( "option", "minDate", selectedDate );
                    }
                });

                $date_to.datepicker({
                    defaultDate: "0",
                    altField: $date_to.siblings('[name="' + $date_to.attr('class') + '"]'),
                    altFormat: 'mm_dd_yy',
                    changeMonth: true,
                    numberOfMonths: 1,
                    onClose: function( selectedDate ) {
                        $date_from.datepicker( "option", "maxDate", selectedDate );
                    }
                });

                $date_from.datepicker('setDate', $date_from.val() || '-1m');
                $date_to.datepicker('setDate', $date_to.val() || '0d');
            });
        }
    };

    $(function () {

        if ($('.wc-mlm-report').length) {
            WC_MLM_Reporting.init();
        }
    });

})(jQuery);