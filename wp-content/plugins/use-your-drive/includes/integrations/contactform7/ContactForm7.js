jQuery(document).ready(function ($) {
    'use strict';

    // Define variables for elements
    let $useyourdrive_module_id = $('#tag-generator-panel-useyourdrive-module-id');
    let $useyourdrive_selector_button = $('button#tag-generator-panel-useyourdrive-module-selector');
    let $useyourdrive_dialog = $('dialog#tag-generator-panel-useyourdrive-module-selector-dialog');
    let $useyourdrive_dialog_close_button = $('button.tag-generator-panel-useyourdrive-module-close');

    // Event handler for selector button click
    $useyourdrive_selector_button.on('click', function (e) {
        e.preventDefault();

        let iFrame = $useyourdrive_dialog.find('iframe');
        let moduleBuilderUrl = iFrame.attr('data-src');

        // Append module ID or default shortcode to URL
        if ($useyourdrive_module_id.val() !== '') {
            moduleBuilderUrl += '&module=' + $useyourdrive_module_id.val();
        } else {
            moduleBuilderUrl +=
                '&shortcode=' +
                WPCP_shortcodeEncode(
                    '[useyourdrive mode="upload" viewrole="all" upload="1" uploadrole="all" upload_auto_start="0" userfolders="auto" viewuserfoldersrole="none"]'
                );
        }

        iFrame.attr('src', moduleBuilderUrl);

        // Show the dialog
        $useyourdrive_dialog[0].showModal();
    });

    // Event handler for dialog close button click
    $useyourdrive_dialog_close_button.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Close the dialog
        $useyourdrive_dialog[0].close();
    });

    // Callback function to add shortcode to CF7 input field
    if (typeof window.wpcp_uyd_cf7_add_content === 'undefined') {
        window.wpcp_uyd_cf7_add_content = function (data) {
            let moduleId = data.match(/module="(\d+)"/)[1];

            $useyourdrive_module_id.val(moduleId);

            // Trigger change and keyup events. Use vanilla JS to trigger events, jQuery events are not working
            let event = new Event('change', { bubbles: true });
            $useyourdrive_module_id[0].dispatchEvent(event);

            event = new Event('keyup', { bubbles: true });
            $useyourdrive_module_id[0].dispatchEvent(event);

            // Close the dialog
            $useyourdrive_dialog[0].close();
        };
    }
});
