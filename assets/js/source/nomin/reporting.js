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
        $actionContainer: null,
        $monthButtons: null,
        $toButton: null,
        $fromButton: null,
        monthSelection: {
            from: 0,
            to: 0
        },

        init: function () {

            this.getElements();

            if (!this.$report.length) {
                return;
            }

            this.setupMonthButtons();
            this.getCurrentMonthButtons();
            this.highlightMonthButtons();
            this.go();
            this.addMonths();
            this.subtractMonths();

            this.setupDatePickers();
        },

        getElements: function () {

            this.$report = $('.wc-mlm-report');
            this.$actionContainer = this.$report.find('.month-select');
            this.$monthButtons = this.$actionContainer.find('.month-button');
        },

        setupMonthButtons: function () {

            var _this = this,
                from = getParameterByName('date_from') || false,
                to = getParameterByName('date_to') || false;

            if (from) {
                this.monthSelection.from = parseInt(from);
            }

            if (to) {
                this.monthSelection.to = parseInt(to);
            }

            this.$monthButtons.click(function (e) {

                e.preventDefault();

                var month = $(this).data('month'),
                    $monthButton = _this.$monthButtons.filter('[data-month="' + month + '"]'),
                    toDistance = Math.abs(_this.$toButton.index() - $monthButton.index()),
                    fromDistance = Math.abs(_this.$fromButton.index() - $monthButton.index());

                // Collapse
                if ($monthButton.index() == _this.$toButton.index()) {
                    _this.monthSelection.from = _this.monthSelection.to;
                }

                if ($monthButton.index() == _this.$fromButton.index()) {
                    _this.monthSelection.to = _this.monthSelection.from;
                }

                // Expand
                if (toDistance == fromDistance && $monthButton.index() > _this.$toButton.index()) {
                    _this.monthSelection.from = month;
                }

                if (toDistance == fromDistance && $monthButton.index() < _this.$toButton.index()) {
                    _this.monthSelection.to = month;
                }

                if (toDistance > fromDistance) {
                    _this.monthSelection.from = month;

                }

                if (fromDistance > toDistance) {
                    _this.monthSelection.to = month;
                }

                _this.monthSelection.to = parseInt(_this.monthSelection.to);
                _this.monthSelection.from = parseInt(_this.monthSelection.from);

                _this.highlightMonthButtons();

                return false;
            });
        },

        getCurrentMonthButtons: function () {
            this.$fromButton = this.$monthButtons.filter('[data-month="' + this.monthSelection.from + '"]');
            this.$toButton = this.$monthButtons.filter('[data-month="' + this.monthSelection.to + '"]');
        },

        highlightMonthButtons: function () {

            this.getCurrentMonthButtons();

            this.$monthButtons.removeClass('highlight from to middle-highlight');

            this.$fromButton.addClass('highlight from');
            this.$toButton.addClass('highlight to');

            for (var i = this.monthSelection.to + 1; i < this.monthSelection.from; i++) {
                this.$monthButtons.filter('[data-month="' + i + '"]').addClass('middle-highlight');
            }
        },

        go: function () {

            var _this = this;

            this.$report.find('.go-button').click(function (e) {

                e.preventDefault();

                var location = window.location.href.split('?')[0];

                location += '?date_from=' + _this.monthSelection.from;
                location += '&date_to=' + _this.monthSelection.to;
                location += '&add_months=' + getParameterByName('add_months');

                window.location.href = location;

                return false;
            });
        },

        addMonths: function () {

            var _this = this;

            this.$report.find('.more-button').click(function (e) {

                e.preventDefault();

                var location = window.location.href.split('?')[0];

                location += '?date_from=' + _this.monthSelection.from;
                location += '&date_to=' + _this.monthSelection.to;
                location += '&add_months=' + $(this).data('add');

                window.location.href = location;

                return false;
            })
        },

        subtractMonths: function () {

            var _this = this;

            this.$report.find('.less-button').click(function (e) {

                e.preventDefault();

                var location = window.location.href.split('?')[0];

                location += '?date_from=' + _this.monthSelection.from;
                location += '&date_to=' + _this.monthSelection.to;
                location += '&add_months=' + $(this).data('add');

                window.location.href = location;

                return false;
            })
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

                // Set defaults
                $date_from.datepicker('setDate', $date_from.val() || '-1m');
                $date_to.datepicker('setDate', $date_to.val() || '0d');

                // Set min / max
                $date_from.datepicker( "option", "maxDate", '0d' );
                $date_to.datepicker( "option", "maxDate", '0d' );
            });
        }
    };

    $(function () {

        if ($('.wc-mlm-report').length) {
            WC_MLM_Reporting.init();
        }
    });

    function getParameterByName(name) {
        name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
        var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
            results = regex.exec(location.search);
        return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
    }

})(jQuery);