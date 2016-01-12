define('js/theme', [
    'jquery',
    'domReady!'
], function ($) {
    'use strict';
    if(jQuery('.ccard').length > 0) {
        jQuery('.ccard').parent().on('submit', function() {
           console.log('123123123');
        });
    }
});