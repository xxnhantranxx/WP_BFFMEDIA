'use strict';

(function () {
    var uyd_toolbarActive = false;

    // CallBack function to add content to Classic MCE editor //
    window.wpcp_add_content_to_mce = function (content) {
        tinymce.activeEditor.execCommand('mceInsertContent', false, content);
        tinymce.activeEditor.windowManager.close();
        tinymce.activeEditor.focus();
    };

    tinymce.create('tinymce.plugins.useyourdrive', {
        init: function (ed, url) {
            var t = this;
            t.url = url;

            ed.addCommand('mceUseyourDrive', function (query) {
                ed.windowManager.open(
                    {
                        file:
                            ajaxurl +
                            '?action=useyourdrive-getpopup&type=modules&' +
                            query +
                            '&callback=wpcp_add_content_to_mce',
                        width: 1280,
                        height: 680,
                        inline: 1,
                    },
                    {
                        plugin_url: url,
                    }
                );
            });
            ed.addCommand('mceUseyourDrive_links', function () {
                ed.windowManager.open(
                    {
                        file: ajaxurl + '?action=useyourdrive-getpopup&type=links&callback=wpcp_add_content_to_mce',
                        width: 1280,
                        height: 680,
                        inline: 1,
                    },
                    {
                        plugin_url: url,
                    }
                );
            });
            ed.addCommand('mceUseyourDrive_embed', function () {
                ed.windowManager.open(
                    {
                        file: ajaxurl + '?action=useyourdrive-getpopup&type=embedded&callback=wpcp_add_content_to_mce',
                        width: 1280,
                        height: 680,
                        inline: 1,
                    },
                    {
                        plugin_url: url,
                    }
                );
            });
            ed.addButton('useyourdrive', {
                title: 'Use-your-Drive module',
                image: url + '/../../css/images/google_drive_logo.png',
                cmd: 'mceUseyourDrive',
            });
            ed.addButton('useyourdrive_links', {
                title: 'Use-your-Drive links',
                image: url + '/../../css/images/google_drive_link.png',
                cmd: 'mceUseyourDrive_links',
            });
            ed.addButton('useyourdrive_embed', {
                title: 'Embed Files from your Drive',
                image: url + '/../../css/images/google_drive_embed.png',
                cmd: 'mceUseyourDrive_embed',
            });

            ed.on('mousedown', function (event) {
                if (ed.dom.getParent(event.target, '#wpcp-mce-toolbar')) {
                    if (tinymce.Env.ie) {
                        // Stop IE > 8 from making the wrapper resizable on mousedown
                        event.preventDefault();
                    }
                } else {
                    removeUydToolbar(ed);
                }
            });

            ed.on('mouseup', function (event) {
                var image,
                    node = event.target,
                    dom = ed.dom;

                // Don't trigger on right-click
                if (event.button && event.button > 1) {
                    return;
                }

                if (node.nodeName === 'DIV' && dom.getParent(node, '#wpcp-mce-toolbar')) {
                    image = dom.select('img[data-wp-uydselect]')[0];

                    if (image) {
                        ed.selection.select(image);

                        if (dom.hasClass(node, 'remove')) {
                            removeUydToolbar(ed);
                            removeUydImage(image, ed);
                        } else if (dom.hasClass(node, 'edit')) {
                            var raw_content = ed.selection.getContent();
                            var shortcode = raw_content.replace('</p>', '').replace('<p>', '');
                            var query = 'shortcode=' + toBinary(shortcode);

                            removeUydToolbar(ed);
                            ed.execCommand('mceUseyourDrive', query);
                        }
                    }
                } else if (
                    node.nodeName === 'IMG' &&
                    !ed.dom.getAttrib(node, 'data-wp-uydselect') &&
                    isUydPlaceholder(node, ed)
                ) {
                    addUydToolbar(node, ed);
                } else if (node.nodeName !== 'IMG') {
                    removeUydToolbar(ed);
                }
            });

            ed.on('keydown', function (event) {
                var keyCode = event.keyCode;
                // Key presses will replace the image so we need to remove the toolbar
                if (uyd_toolbarActive) {
                    if (
                        event.ctrlKey ||
                        event.metaKey ||
                        event.altKey ||
                        (keyCode < 48 && keyCode > 90) ||
                        keyCode > 186
                    ) {
                        return;
                    }

                    removeUydToolbar(ed);
                }
            });

            ed.on('cut', function () {
                removeUydToolbar(ed);
            });

            ed.on('BeforeSetcontent', function (ed) {
                ed.content = t._do_uyd_shortcode(ed.content, t.url);
            });
            ed.on('PostProcess', function (ed) {
                if (ed.get) ed.content = t._get_uyd_shortcode(ed.content);
            });
        },
        _do_uyd_shortcode: function (co, url) {
            return co.replace(/\[useyourdrive([^\]]*)\]/g, function (a, b) {
                return (
                    '<img src="' +
                    url +
                    '/../../css/images/transparant.png" class="wpcp-mce-shortcode wpcp-mce-useyourdrive-shortcode mceItem" title="Use-your-Drive" data-mce-placeholder="1" data-code="' +
                    toBinary(b) +
                    '"/>'
                );
            });
        },
        _get_uyd_shortcode: function (co) {
            function getAttr(s, n) {
                n = new RegExp(n + '="([^"]+)"', 'g').exec(s);
                return n ? n[1] : '';
            }

            return co.replace(/(?:<p[^>]*>)*(<img[^>]+>)(?:<\/p>)*/g, function (a, im) {
                var cls = getAttr(im, 'class');

                if (cls.indexOf('wpcp-mce-useyourdrive-shortcode') != -1)
                    return '<p>[useyourdrive ' + tinymce.trim(fromBinary(getAttr(im, 'data-code'))) + ']</p>';

                return a;
            });
        },
        createControl: function (n, cm) {
            return null;
        },
    });

    tinymce.PluginManager.add('useyourdrive', tinymce.plugins.useyourdrive);

    function removeUydImage(node, editor) {
        editor.dom.remove(node);
        removeUydToolbar(editor);
    }

    function addUydToolbar(node, editor) {
        var toolbarHtml,
            toolbar,
            dom = editor.dom;

        removeUydToolbar(editor);

        // Don't add to placeholders
        if (!node || node.nodeName !== 'IMG' || !isUydPlaceholder(node, editor)) {
            return;
        }

        dom.setAttrib(node, 'data-wp-uydselect', 1);

        toolbarHtml =
            '<div class="dashicons dashicons-edit edit" data-mce-bogus="1"></div>' +
            '<div class="dashicons dashicons-no-alt remove" data-mce-bogus="1"></div>';

        toolbar = dom.create(
            'div',
            {
                id: 'wpcp-mce-toolbar',
                'data-mce-bogus': '1',
                contenteditable: false,
                class: 'wpcp-mce-toolbar',
            },
            toolbarHtml
        );

        var parentDiv = node.parentNode;
        parentDiv.insertBefore(toolbar, node);

        uyd_toolbarActive = true;
    }

    function removeUydToolbar(editor) {
        var toolbar = editor.dom.get('wpcp-mce-toolbar');

        if (toolbar) {
            editor.dom.remove(toolbar);
        }

        editor.dom.setAttrib(editor.dom.select('img[data-wp-uydselect]'), 'data-wp-uydselect', null);

        uyd_toolbarActive = false;
    }

    function isUydPlaceholder(node, editor) {
        var dom = editor.dom;

        if (dom.hasClass(node, 'wpcp-mce-useyourdrive-shortcode')) {
            return true;
        }

        return false;
    }
    function toBinary(str) {
        return btoa(
            encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function toSolidBytes(match, p1) {
                return String.fromCharCode('0x' + p1);
            })
        );
    }

    function fromBinary(str) {
        return decodeURIComponent(
            atob(str)
                .split('')
                .map(function (c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                })
                .join('')
        );
    }
})();
