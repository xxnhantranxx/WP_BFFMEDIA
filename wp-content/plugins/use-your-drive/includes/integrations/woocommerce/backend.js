jQuery(function ($) {
    var useyourdrive_wc = {
        // hold a reference to the last selected Google Drive button
        lastSelectedButton: false,
        module: $('#wpcp-modal-selector-google .wpcp-module'),

        init: function () {
            // place wpcp container bottom body
            $('#wpcp-modal-selector-google').parent().appendTo('body');

            // add button for simple product
            this.addButtons();
            this.addButtonEventHandler();
            // add buttons when variable product added
            $('#variable_product_options').on('woocommerce_variations_added', function () {
                useyourdrive_wc.addButtons();
            });
            // add buttons when variable products loaded
            $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
                useyourdrive_wc.addButtons();
            });

            // Select the already added files in the File Browser module
            this.initSelectAdded();
            this.initAddButton();

            return this;
        },

        addButtons: function () {
            let self = this;

            var button = $(
                '<a class="button wpcp-insert-google-content">' +
                    useyourdrive_woocommerce_translation.choose_from +
                    '</a>'
            );

            $('.downloadable_files').each(function (index) {
                // we want our button to appear next to the insert button
                var insertButton = $(this).find('a.button.insert');
                // check if button already exists on element, bail if so
                if ($(this).find('a.button.wpcp-insert-google-content').length > 0) {
                    return;
                }

                // finally clone the button to the right place
                insertButton.after(button.clone());
            });

            /* START Support for WooCommerce Product Documents */
            $('.wc-product-documents .button.wc-product-documents-set-file').each(function (index) {
                // check if button already exists on element, bail if so
                if ($(this).parent().find('a.button.wpcp-insert-google-content').length > 0) {
                    return;
                }

                // finally clone the button to the right place
                $(this).after(button.clone());
            });

            $('#wc-product-documents-data').on('click', '.wc-product-documents-add-document', function () {
                self.addButtons();
            });
            /* END Support for WooCommerce Product Documents */
        },
        /**
         * Adds the click event to the buttons
         * and opens the Google Drive chooser
         */
        addButtonEventHandler: function () {
            let self = this;

            $('#woocommerce-product-data').on('click', 'a.button.wpcp-insert-google-content', function (e) {
                self.openSelector();
                e.preventDefault();

                // save a reference to clicked button
                useyourdrive_wc.lastSelectedButton = $(this);
            });

            $('#wpcp-modal-selector-google .wpcp-dialog-close').on('click', function (e) {
                self.closeSelector();
            });

            $('#wpcp-modal-selector-google .wpcp-wc-dialog-entry-select').on('click', function (e) {
                const account_id = self.module.attr('data-account-id');
                const entries_data = self.module
                    .find("input[name='selected-files[]']:checked")
                    .map(function () {
                        const $entry = $(this).parents('.entry');

                        return {
                            entry_id: $entry.attr('data-id'),
                            entry_name: $entry.attr('data-name'),
                            account_id: account_id,
                        };
                    })
                    .get();

                if (entries_data.length === 0) {
                    return self.closeSelector();
                }

                // Send the data via postMessage
                window.top.postMessage(
                    {
                        slug: 'useyourdrive',
                        action: 'wpcp-select-entries',
                        entries: entries_data,
                    },
                    window.location.origin
                );

                setTimeout(function () {
                    self.closeSelector();
                }, 100);
            });
        },

        openSelector: function () {
            let self = this;

            window.addEventListener('message', useyourdrive_wc.afterFileSelected);

            $('#wpcp-modal-selector-google').show();
            $('#wpcp-modal-selector-google .wpcp-wc-dialog-entry-select').prop('disabled', 'disabled');
        },

        closeSelector: function () {
            window.removeEventListener('message', useyourdrive_wc.afterFileSelected);
            $('#wpcp-modal-selector-google').fadeOut();

            useyourdrive_wc.lastSelectedButton = null;
        },

        /**
         * Mark already added file in the File Browser moulde
         */
        initSelectAdded: function () {
            const self = this;

            self.module.on('wpcp-content-loaded', function (e, plugin) {
                plugin.element
                    .find("input[name='selected-files[]']:checked")
                    .prop('checked', false)
                    .removeClass('is-selected');

                const added_files = $(useyourdrive_wc.lastSelectedButton)
                    .closest('.downloadable_files')
                    .find('.file_url > input')
                    .filter(function (index) {
                        return $(this).val().includes('drive.google.com');
                    })
                    .toArray();

                added_files.forEach(function (input, index, array) {
                    const url = new URL($(input).val());
                    const entry_id = url.searchParams.get('id');
                    const account_id = url.searchParams.get('account_id');

                    // Show the entry as selected
                    $('.wpcp-module[data-account-id="' + account_id + '"] .entry[data-id="' + entry_id + '"]').addClass(
                        'is-selected'
                    );
                });
            });
        },

        /**
         * Enable & Disable add button based on selection of entries
         */
        initAddButton: function () {
            let self = this;
            $(self.module).on(
                {
                    change: function (e) {
                        if (self.module.find("input[name='selected-files[]']:checked").length) {
                            $('#wpcp-modal-selector-google .wpcp-wc-dialog-entry-select').prop('disabled', '');
                        } else {
                            $('#wpcp-modal-selector-google .wpcp-wc-dialog-entry-select').prop('disabled', 'disabled');
                        }
                    },
                },
                "input[name='selected-files[]']"
            );
        },

        /**
         * Handle selected files
         */
        afterFileSelected: function (event) {
            if (event.origin !== window.location.origin) {
                return;
            }

            if (typeof event.data !== 'object' || event.data === null || typeof event.data.action === 'undefined') {
                return;
            }

            if (event.data.action !== 'wpcp-select-entries') {
                return;
            }

            if (event.data.slug !== 'useyourdrive') {
                return;
            }

            let files_added = [];
            let files_failed = [];

            event.data.entries.forEach(function (entry, index, array) {
                // Make sure only a single instance of the file can be added
                if (
                    $(useyourdrive_wc.lastSelectedButton)
                        .closest('.downloadable_files')
                        .find('.file_url > input')
                        .filter(function (index) {
                            return $(this)
                                .val()
                                .includes(entry.entry_id + '&account_id=' + entry.account_id);
                        }).length
                ) {
                    files_failed.push(entry.entry_name);
                    return false;
                }

                if ($(useyourdrive_wc.lastSelectedButton).closest('.downloadable_files').length > 0) {
                    var table = $(useyourdrive_wc.lastSelectedButton).closest('.downloadable_files').find('tbody');
                    var template = $(useyourdrive_wc.lastSelectedButton)
                        .parent()
                        .find('.button.insert:first')
                        .data('row');
                    var fileRow = $(template);

                    fileRow.find('.file_name > input:first').val(entry.entry_name).change();
                    fileRow
                        .find('.file_url > input:first')
                        .val(
                            useyourdrive_woocommerce_translation.download_url +
                                entry.entry_id +
                                '&account_id=' +
                                entry.account_id
                        );
                    table.append(fileRow);

                    // trigger change event so we can save variation
                    $(table).find('input').last().change();
                }

                /* START Support for WooCommerce Product Documents */
                if ($(useyourdrive_wc.lastSelectedButton).closest('.wc-product-document').length > 0) {
                    var row = $(useyourdrive_wc.lastSelectedButton).closest('.wc-product-document');

                    row.find('.wc-product-document-label input:first').val(entry.entry_name).change();
                    row.find('.wc-product-document-file-location input:first').val(
                        useyourdrive_woocommerce_translation.wcpd_url +
                            entry.entry_id +
                            '&account_id=' +
                            entry.account_id
                    );
                }
                /* END Support for WooCommerce Product Documents */

                // Show the entry as selected
                $(
                    '.wpcp-module[data-account-id="' + entry.account_id + '"] .entry[data-id="' + entry.entry_id + '"]'
                ).addClass('is-selected');

                files_added.push(entry.entry_name);
            });

            if (files_failed.length) {
                window.showNotification(
                    false,
                    useyourdrive_woocommerce_translation.notification_failed_file_msg.replace(
                        '{filename}',
                        '<strong>' + files_failed.join(', ') + '</strong>'
                    )
                );
            }

            if (files_added.length) {
                window.showNotification(
                    true,
                    useyourdrive_woocommerce_translation.notification_success_file_msg.replace(
                        '{filename}',
                        '<strong>' + files_added.join(', ') + '</strong>'
                    )
                );
            }
        },
    };
    window.useyourdrive_wc = useyourdrive_wc.init();

    /* Callback function to add shortcode to WC field */
    if (typeof window.wpcp_uyd_wc_add_content === 'undefined') {
        window.wpcp_uyd_wc_add_content = function (data) {
            $('#useyourdrive_upload_box_shortcode').val(data);
            window.modal_action.close();
            $('#wpcp-modal-action.UseyourDrive').remove();
        };
    }

    $('input#_uploadable').on('change', function () {
        var is_uploadable = $('input#_uploadable:checked').length;
        $('.show_if_uploadable').hide();
        $('.hide_if_uploadable').hide();
        if (is_uploadable) {
            $('.hide_if_uploadable').hide();
        }
        if (is_uploadable) {
            $('.show_if_uploadable').show();
        }
    });
    $('input#_uploadable').trigger('change');

    $('input#useyourdrive_upload_box').on('change', function () {
        var useyourdrive_upload_box = $('input#useyourdrive_upload_box:checked').length;
        $('.show_if_useyourdrive_upload_box').hide();
        if (useyourdrive_upload_box) {
            $('.show_if_useyourdrive_upload_box').show();
        }
    });
    $('input#useyourdrive_upload_box').trigger('change');

    /* Shortcode Generator Popup */
    $('.wpcp-insert-google-shortcode').on('click', function (e) {
        let shortcode = $('#useyourdrive_upload_box_shortcode').val();

        openShortcodeBuilder(shortcode);
    });

    function openShortcodeBuilder(shortcode) {
        if ($('#wpcp-modal-action.UseyourDrive').length > 0) {
            window.modal_action.close();
            $('#wpcp-modal-action.UseyourDrive').remove();
        }

        /* Build the  Dialog */
        let modalbuttons = '';
        let modalheader = $(
            `<div class="wpcp-modal-header" tabindex="0">                          
                    <a tabindex="0" class="close-button"  onclick="window.modal_action.close();"><i class="eva eva-close eva-lg" aria-hidden="true"></i></a>
                </div>`
        );
        let modalbody = $('<div class="wpcp-modal-body" tabindex="0" style="display:none;padding:0!important;"></div>');
        let modaldialog = $(
            '<div id="wpcp-modal-action" class="UseyourDrive wpcp wpcp-modal wpcp-modal80 wpcp-modal-minimal light"><div class="modal-dialog"><div class="modal-content"><div class="loading"><div class="loader-beat"></div></div></div></div></div>'
        );

        $('body').append(modaldialog);

        var query = 'shortcode=' + WPCP_shortcodeEncode(shortcode);
        var $iframe_template = $(
            "<iframe src='" +
                window.ajaxurl +
                '?action=useyourdrive-getpopup&type=modules&foruploadfield=1&callback=wpcp_uyd_wc_add_content&' +
                query +
                "' width='100%' height='600' tabindex='-1' style='border:none' title=''></iframe>"
        );
        var $iframe = $iframe_template.appendTo(modalbody);

        $('#wpcp-modal-action.UseyourDrive .modal-content').append(modalheader, modalbody);

        $iframe.on('load', function () {
            $('.wpcp-modal-body').fadeIn();
            $('.wpcp-modal-footer').fadeIn();
            $('.modal-content .loading:first').fadeOut();
        });

        /* Open the Dialog */
        let modal_action = new RModal(document.getElementById('wpcp-modal-action'), {
            bodyClass: 'rmodal-open',
            dialogOpenClass: 'animated slideInDown',
            dialogCloseClass: 'animated slideOutUp',
            escapeClose: true,
        });
        document.addEventListener(
            'keydown',
            function (ev) {
                modal_action.keydown(ev);
            },
            false
        );
        modal_action.open();
        window.modal_action = modal_action;
    }
});
