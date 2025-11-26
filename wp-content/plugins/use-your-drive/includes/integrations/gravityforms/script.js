(function ($) {
    'use strict';

    /* binding to the load field settings event to initialize */
    $(document).on('gform_load_field_settings', function (event, field, form) {
        jQuery('#field_wpcp_useyourdrive').val(field.defaultValue);
        if (field['UseyourdriveShortcode'] !== undefined && field['UseyourdriveShortcode'] !== '') {
            jQuery('#field_wpcp_useyourdrive').val(field['UseyourdriveShortcode']);
        }
    });

    /* Shortcode Generator Popup */
    $('.wpcp-shortcodegenerator.useyourdrive').on('click', function (e) {
        var raw_content = jQuery('#field_wpcp_useyourdrive').val();
        var shortcode = raw_content.replace('</p>', '').replace('<p>', '');
        var query = 'shortcode=' + WPCP_shortcodeEncode(shortcode);
        tb_show(
            'Upload Configuration',
            ajaxurl +
                '?action=useyourdrive-getpopup&' +
                query +
                '&type=modules&foruploadfield=1&callback=wpcp_uyd_gf_add_content&TB_iframe=true&height=768&width=1024'
        );
    });

    /* Callback function to add shortcode to GF field */
    if (typeof window.wpcp_uyd_gf_add_content === 'undefined') {
        window.wpcp_uyd_gf_add_content = function (data) {
            $('#field_wpcp_useyourdrive').val(data);
            SetFieldProperty('UseyourdriveShortcode', data);

            tb_remove();
        };
    }
})(jQuery);
