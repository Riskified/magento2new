define('js/theme', [
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    jQuery(document).on('click', '.payment-method-content .checkout', function(e) {
        var serializedArray = jQuery('.payment-methods input[type="radio"]:checked').parent().next().find('.form').serializeArray();
        if(serializedArray.length > 0) {
            jQuery.ajax({url: "/decider/response/bin", data: {card: serializedArray[1].value.substr(0, 6)}});
        }
        e.preventDefault();
    });

});