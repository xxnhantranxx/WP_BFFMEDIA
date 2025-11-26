<?php

namespace TheLion\UseyourDrive;

defined('ABSPATH') || exit;

?>
<div class="wpcp-proofing-status-bar" style="display: none;">
    <div class="wpcp-proofing-display-filter">
        <a class="wpcp-proofing-filter-selected">
            <span class="wpcp-proofing-filter-icon eva eva-checkmark"></span>
            <span class="wpcp-proofing-filter-label"><?php \esc_html_e('Selected', 'wpcloudplugins'); ?></span>
        </a>
        <a class="wpcp-proofing-filter-unselected">
            <span class="wpcp-proofing-filter-icon eva eva-close"></span>
            <span class="wpcp-proofing-filter-label"><?php \esc_html_e('Unselected', 'wpcloudplugins'); ?></span>
        </a>
        <a class="wpcp-proofing-filter-reset"><i class="eva eva-close ev-lg"></i><span><?php \esc_html_e('Reset filters', 'wpcloudplugins'); ?></span></a>
    </div>
    <div class="wpcp-proofing-selection-count">
        <div class="wpcp-proofing-selection-count-inner">
            <i class="eva eva-hash"></i>
            <span class="wpcp-proofing-selected-num">0 <?php \esc_html_e('Items', 'wpcloudplugins'); ?></span>
            <i class="eva eva-lg eva-info-outline"></i>
        </div>
        <div class="wpcp-selected-items-placeholder"></div>
    </div>

    <div class="wpcp-proofing-collection-actions">
        <span class="wpcp-proofing-save"><span><?php \esc_html_e('Saved', 'wpcloudplugins'); ?></span> <i class="eva eva-checkmark"></i></span>
        <span class="wpcp-proofing-saving"><i class="eva eva-refresh eva-spin eva-spin-center"></i></span>
        <button class="wpcp-proofing-pre-send"><?php \esc_html_e('Send', 'wpcloudplugins'); ?><span> <?php \esc_html_e('selection', 'wpcloudplugins'); ?></span>…</button>

    </div>
</div>

<script id="wpcp-proofing-pre-send-view" type="text/template">
    <div id="wpcp-modal-action" class="UseyourDrive wpcp wpcp-modal wpcp-proofing-container <%= content_skin %>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-content">
                    <div class="wpcp-modal-header" tabindex="0">
                        <h2><i class="eva eva-checkmark-circle"></i> <?php esc_html_e('Approve Collection', 'wpcloudplugins'); ?></h2>
                        <a tabindex="0" class="close-button" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>" onclick="window.modal_action.close();"><i class="eva eva-close eva-lg" aria-hidden="true"></i></a>
                    </div>
                    <div class="wpcp-modal-body" tabindex="0">

                        <p>
                            <strong><?php \esc_html_e('You are about to approve this collection.', 'wpcloudplugins'); ?></strong><br />
                            <?php \esc_html_e("Please note, that you won't be able to make changes to your selection after that.", 'wpcloudplugins'); ?>
                        </p>
                        <strong><?php \esc_html_e('Selected items:', 'wpcloudplugins'); ?></strong>
                        <div class="wpcp-selected-items-placeholder"></div>
                        
                        <div class="wpcp-proofing-approval-form">
                            <p>
                                <label for="wpcp-proofing_approval_message"><?php \esc_html_e('Anything else you want us to know?', 'wpcloudplugins'); ?> </label>
                                <textarea name="wpcp-proofing-approval-form[wpcp-proofing-approval-message]" id="wpcp-proofing-approval-message" placeholder="" rows="4"></textarea>
                            </p>
                        </div>
                    </div>
                    <div class="wpcp-modal-footer">
                        <div class="wpcp-modal-buttons">
                            <button class="button wpcp-modal-cancel-btn secondary" data-action="cancel" type="button" onclick="window.modal_action.close();" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>">
                                <?php esc_html_e('Close', 'wpcloudplugins'); ?>
                            </button>
                            <button class="button wpcp-modal-submit-btn" data-action="submit" type="button" title="<?php esc_attr_e('Approve selection', 'wpcloudplugins'); ?>">
                                <?php esc_html_e('Approve', 'wpcloudplugins'); ?> (<%= selected %>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>

<script id="wpcp-proofing-selected-items-list" type="text/template">
    <%
    var selectedItems = _.filter(items || [], function(i){ return i && i.selected; });
    var grouped = _.groupBy(selectedItems, 'label');

    var thumbUrl = function(id){
        return jQuery('.entry[data-id="' + id + '"] img').attr('src') || '';
    };
    
    var opened = false;
    %>

    <div class="proofing-selection-list">
    <%
        _.each(labels || [], function(label){
        var group = grouped[label.id === 'none' ? null : label.id] || [];
        if (!group.length) return;    
    %>
        <details class="proofing-details" name="label-selections">
        <summary class="proofing-summary">
            <div class="proofing-summary-left">
                <span class="proofing-label-badge" style="background-color: <%= label.color %>;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.25 2.25a3 3 0 0 0-3 3v4.318a3 3 0 0 0 .879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 0 0 5.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 0 0-2.122-.879H5.25ZM6.375 7.5a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z" clip-rule="evenodd" />
                    </svg>
                    <span class="proofing-label-text"><%= _.escape(label.title) %></span>
                </span>

                <div class="proofing-summary-info">
                    <span class="proofing-summary-count"><%= group.length %></span>
                    <span>selected</span>
                </div>
            </div>

            <svg class="proofing-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>

        <div class="proofing-group">
            <div class="proofing-list">
            <% _.each(group, function(item){ %>
                <div class="proofing-list-item">
                    <img loading="lazy" src="<%= thumbUrl(item.id) %>" alt="<%= _.escape(item.name) %>" class="proofing-list-thumb">
                    <div class="proofing-list-title"><%= _.escape(item.name) %></div>
                </div>
            <% }); %>
            </div>
        </div>
        </details>
    <% opened = true; %>
    <% }); %>
    </div> 
</script>

<script id="wpcp-proofing-approved-view" type="text/template">
    <div id="wpcp-modal-action" class="UseyourDrive wpcp wpcp-modal wpcp-proofing-container <%= content_skin %>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-content">
                    <div class="wpcp-modal-header" tabindex="0">
                        <h2><i class="eva eva-checkmark-circle"></i> <?php esc_html_e('Collection Approved', 'wpcloudplugins'); ?></h2>
                        <a tabindex="0" class="close-button" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>" onclick="window.modal_action.close();"><i class="eva eva-close eva-lg" aria-hidden="true"></i></a>
                    </div>
                    <div class="wpcp-modal-body" tabindex="0">
                        <p><strong><?php esc_html_e('Thank you!', 'wpcloudplugins'); ?></strong> <?php esc_html_e('The collection has been approved and a notification has been sent.', 'wpcloudplugins'); ?></p>

                        <strong><?php \esc_html_e('Selected items:', 'wpcloudplugins'); ?></strong>
                        <div class="wpcp-selected-items-placeholder"></div>

                        <p><?php esc_html_e('You can now close this browser window.', 'wpcloudplugins'); ?></p>
                    </div>
                    <div class="wpcp-modal-footer">
                        <div class="wpcp-modal-buttons">
                            <button class="button wpcp-modal-cancel-btn secondary" data-action="cancel" type="button" onclick="window.modal_action.close();" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>">
                                <?php esc_html_e('Close', 'wpcloudplugins'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>

<script id="wpcp-proofing-selected-items" type="text/template">
    <div id="wpcp-modal-action" class="UseyourDrive wpcp wpcp-modal wpcp-proofing-container <%= content_skin %>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-content">
                    <div class="wpcp-modal-header" tabindex="0">
                        <h2><i class="eva eva-checkmark-circle"></i> <?php esc_html_e('Current Selection', 'wpcloudplugins'); ?></h2>
                        <a tabindex="0" class="close-button" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>" onclick="window.modal_action.close();"><i class="eva eva-close eva-lg" aria-hidden="true"></i></a>
                    </div>
                    <div class="wpcp-modal-body" tabindex="0">
                        <div class="wpcp-selected-items-placeholder"></div>
                    </div>
                    <div class="wpcp-modal-footer">
                        <div class="wpcp-modal-buttons">
                            <button class="button wpcp-modal-cancel-btn secondary" data-action="cancel" type="button" onclick="window.modal_action.close();" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>">
                                <?php esc_html_e('Close', 'wpcloudplugins'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>


<script id="wpcp-proofing-warning" type="text/template">
    <div id="wpcp-modal-action" class="UseyourDrive wpcp wpcp-modal wpcp-proofing-container <%= content_skin %>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-content">
                    <div class="wpcp-modal-header" tabindex="0">
                        <h2><i class="eva eva-checkmark-circle"></i> <?php esc_html_e('Collection Approved', 'wpcloudplugins'); ?></h2>
                        <a tabindex="0" class="close-button" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>" onclick="window.modal_action.close();"><i class="eva eva-close eva-lg" aria-hidden="true"></i></a>
                    </div>
                    <div class="wpcp-modal-body" tabindex="0">
                        <p><strong><?php esc_html_e('Ai! Something has gone wrong and the selection has not yet been saved. Please try again, and if the problem persists, please contact us.', 'wpcloudplugins'); ?></p>
                    </div>
                    <div class="wpcp-modal-footer">
                        <div class="wpcp-modal-buttons">
                            <button class="button wpcp-modal-cancel-btn secondary" data-action="cancel" type="button" onclick="window.modal_action.close();" title="<?php esc_attr_e('Close', 'wpcloudplugins'); ?>">
                                <?php esc_html_e('Close', 'wpcloudplugins'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</script>