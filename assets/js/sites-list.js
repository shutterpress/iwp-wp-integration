/**
 * Sites list page UI for the FluentCart InstaWP Provisioning admin.
 *
 * Currently wires the Show/Hide toggle for the Password column rendered by
 * FCIP_Sites_List_Table::column_password().
 *
 * @package FCIP
 */

(function ($) {
    'use strict';

    var labels = (window.fcipSitesList && window.fcipSitesList.labels) || {
        show: 'Show',
        hide: 'Hide'
    };

    $(document).on('click', '.fcip-show-password', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $revealed = $btn.next('.fcip-password-revealed');
        if ($btn.text() === labels.show) {
            $revealed.text($btn.data('password'));
            $btn.text(labels.hide);
        } else {
            $revealed.text('');
            $btn.text(labels.show);
        }
    });
}(jQuery));

