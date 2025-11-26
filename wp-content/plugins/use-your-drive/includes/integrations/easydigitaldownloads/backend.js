jQuery(function ($) {
    var useyourdrive_edd = {
        // hold a reference to the last selected Google Drive button
        lastSelectedButton: false,
        module: $('#wpcp-modal-selector-google .wpcp-module'),

        init: function () {
            // place wpcp container bottom body
            $('#wpcp-modal-selector-google').parent().appendTo('body');

            // add File button
            this.addButtons();
            this.addButtonEventHandler();

            // Select the already added files in the File Browser module
            this.initSelectAdded();
            this.initAddButton();

            return this;
        },

        addButtons: function () {
            let self = this;

            var button = $(
                '<a class="button wpcp-insert-google-content">' + useyourdrive_edd_translation.choose_from + '</a>'
            );

            if ($(this).find('a.button.wpcp-insert-google-content').length > 0) {
                return;
            }

            button.clone().insertBefore($('.edd_add_repeatable'));
        },
        /**
         * Adds the click event to the buttons
         * and opens the File Chooser
         */
        addButtonEventHandler: function () {
            let self = this;

            $('#edd_download_files').on('click', 'a.button.wpcp-insert-google-content', function (e) {
                self.openSelector();
                e.preventDefault();

                // save a reference to clicked button
                useyourdrive_edd.lastSelectedButton = $(this);
            });

            $('#wpcp-modal-selector-google .wpcp-dialog-close').on('click', function (e) {
                self.closeSelector();
            });

            $('#wpcp-modal-selector-google .wpcp-edd-dialog-entry-select').on('click', function (e) {
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

            window.addEventListener('message', useyourdrive_edd.afterFileSelected);

            $('#wpcp-modal-selector-google').show();
            $('#wpcp-modal-selector-google .wpcp-edd-dialog-entry-select').prop('disabled', 'disabled');
        },

        closeSelector: function () {
            window.removeEventListener('message', useyourdrive_edd.afterFileSelected);
            $('#wpcp-modal-selector-google').fadeOut();
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

                const added_files = $('#edd_download_files input.edd_repeatable_upload_field')
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
                            $('#wpcp-modal-selector-google .wpcp-edd-dialog-entry-select').prop('disabled', '');
                        } else {
                            $('#wpcp-modal-selector-google .wpcp-edd-dialog-entry-select').prop('disabled', 'disabled');
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
            let self = this;

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
                    $('#edd_download_files input.edd_repeatable_upload_field').filter(function (index) {
                        return $(this)
                            .val()
                            .includes(entry.entry_id + '&account_id=' + entry.account_id);
                    }).length
                ) {
                    files_failed.push(entry.entry_name);
                    return false;
                }

                if ($(useyourdrive_edd.lastSelectedButton).closest('#edd_download_files').length > 0) {
                    let fileRow = $(useyourdrive_edd.lastSelectedButton)
                        .closest('#edd_download_files')
                        .find('.edd_repeatable_row:last');

                    fileRow.find('input.edd_repeatable_name_field').val(entry.entry_name).change();
                    fileRow
                        .find('input.edd_repeatable_upload_field')
                        .val(
                            'https://drive.google.com/open' +
                                encodeURIComponent(entry.entry_name) +
                                useyourdrive_edd_translation.download_url +
                                entry.entry_id +
                                '&account_id=' +
                                entry.account_id
                        );

                    // Add a new row to the Download file section
                    $('.edd_add_repeatable').trigger('click');
                }

                // Show the entry as selected
                $(
                    '.wpcp-module[data-account-id="' + entry.account_id + '"] .entry[data-id="' + entry.entry_id + '"]'
                ).addClass('is-selected');

                files_added.push(entry.entry_name);
            });

            if (files_failed.length) {
                window.showNotification(
                    false,
                    useyourdrive_edd_translation.notification_failed_file_msg.replace(
                        '{filename}',
                        '<strong>' + files_failed.join(', ') + '</strong>'
                    )
                );
            }

            if (files_added.length) {
                window.showNotification(
                    true,
                    useyourdrive_edd_translation.notification_success_file_msg.replace(
                        '{filename}',
                        '<strong>' + files_added.join(', ') + '</strong>'
                    )
                );
            }

            window.useyourdrive_edd.closeSelector();
        },
    };
    window.useyourdrive_edd = useyourdrive_edd.init();
});
