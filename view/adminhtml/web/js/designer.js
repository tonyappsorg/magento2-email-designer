define([
    'jquery',
    'underscore',
    'mage/translate',
    'grapesjs',
    'grapesjs-mjml',
    'Tonyapps_EmailDesigner/js/directives',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/prompt',
    'Magento_Ui/js/modal/modal'
], function ($, _, $t, grapesjs, grapesjsMjml, directives, alert, confirm, prompt, modal) {
    'use strict';

    var mjmlPlugin = grapesjsMjml.default || grapesjsMjml,
        BLOCK_TYPE = 'ta-block';

    function post(url, data) {
        return $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            showLoader: true,
            data: $.extend({form_key: window.FORM_KEY}, data)
        });
    }

    function fail(message) {
        alert({title: $t('Email Designer'), content: _.escape(message || $t('Something went wrong. Try again.'))});
    }

    function variableSelect(variables) {
        return '<select class="gjs-field" title="' + _.escape($t('Insert a value')) + '">'
            + '<option value="">{ } ' + _.escape($t('Insert value')) + '</option>'
            + _.map(variables, function (variable) {
                return '<option value="' + _.escape(variable.value) + '">' + _.escape(variable.label) + '</option>';
            }).join('')
            + '</select>';
    }

    function addMagentoBlocks(editor, config) {
        var blocks = editor.BlockManager,
            category = 'Magento';

        blocks.add('ta-store-logo', {
            label: $t('Store logo'),
            category: category,
            media: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M4 5h16v14H4zm2 2v10h12V7zm2 8 2.5-3 2 2.5L15 11l3 4z"/></svg>',
            content: '<mj-image src="' + _.escape(config.logoUrl) + '" css-class="' + directives.LOGO_CLASS
                + '" alt="{{var logo_alt}}" width="180px" align="left"></mj-image>'
        });
        blocks.add('ta-store-name', {
            label: $t('Store name'),
            category: category,
            media: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16l1 5a3 3 0 0 1-5 2 3 3 0 0 1-4 0 3 3 0 0 1-4 0 3 3 0 0 1-5-2zm1 9h14v7H5z"/></svg>',
            content: '<mj-text font-size="18px" font-weight="600">{{var store.frontend_name}}</mj-text>'
        });
        blocks.add('ta-store-contact', {
            label: $t('Store contact'),
            category: category,
            media: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 5h18v14H3zm2 2v.5l7 4.5 7-4.5V7zm0 2.9V17h14V9.9l-7 4.5z"/></svg>',
            content: '<mj-text font-size="12px" color="#999999" align="center">{{var store.frontend_name}}'
                + ' · {{var store_email}}{{depend store_phone}} · {{var store_phone}}{{/depend}}</mj-text>'
        });
    }

    /**
     * A Magento block: one box in the canvas, a directive in the email. MJML passes mj-raw through as is;
     * inside a column it must be a table row, like the rows MJML renders around other content.
     */
    function addBlockType(editor, config) {
        editor.Components.addType(BLOCK_TYPE, {
            extend: 'mj-raw',
            // Starter designs write a block as <mj-raw ta-block="id"></mj-raw>; unknown ids stay plain mj-raw.
            isComponent: function (el) {
                var id = el.tagName === 'MJ-RAW' && el.getAttribute('ta-block'),
                    block = id && _.findWhere(config.blocks || [], {id: id});

                return block
                    ? {type: BLOCK_TYPE, name: block.label, taLabel: block.label, taDirective: block.directive, components: []}
                    : false;
            },
            model: {
                defaults: {
                    name: $t('Magento block'),
                    draggable: function (component, target) {
                        return target.get('type') === 'mj-column';
                    },
                    droppable: false,
                    editable: false,
                    stylable: false,
                    taLabel: '',
                    taDirective: ''
                },
                toHTML: function () {
                    return '<mj-raw><tr><td style="padding:10px 25px;font-size:14px;line-height:1.6;color:#333333;'
                        + 'font-family:Helvetica, Arial, sans-serif;">' + this.get('taDirective') + '</td></tr></mj-raw>';
                }
            },
            view: {
                tagName: 'tr',
                attributes: {style: 'pointer-events: all; display: table; width: 100%'},
                init: function () {},
                render: function () {
                    this.updateAttributes();
                    this.el.innerHTML = '<td style="padding:10px 25px"><div style="border:1px dashed #9f9f9f;'
                        + 'padding:14px;text-align:center;color:#666;font:13px Helvetica, Arial, sans-serif;">'
                        + _.escape(this.model.get('taLabel')) + '</div></td>';
                    return this;
                }
            }
        });
    }

    function addEmailBlocks(editor, config) {
        _.each(config.blocks || [], function (block) {
            editor.BlockManager.add('ta-block-' + block.id, {
                label: block.label,
                category: $t('This email'),
                media: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 4h18v4H3zm0 6h18v4H3zm0 6h18v4H3z"/></svg>',
                content: {type: BLOCK_TYPE, name: block.label, taLabel: block.label, taDirective: block.directive}
            });
        });
    }

    function addVariableAction(editor, variables) {
        editor.RichTextEditor.add('ta-variable', {
            icon: variableSelect(variables),
            event: 'change',
            result: function (rte, action) {
                var select = action.btn.querySelector('select');

                if (select.value) {
                    rte.insertHTML(select.value);
                }
                select.value = '';
            },
            update: function (rte, action) {
                action.btn.querySelector('select').value = '';
            }
        });
    }

    return function (config, element) {
        var $root = $(element),
            $name = $root.find('[data-role=name]').val(config.name),
            $subject = $root.find('[data-role=subject]').val(config.subject),
            $versions = $root.find('[data-role=versions]'),
            dirty = false,
            editor;

        $root.find('[data-role=for]').text($t('Design for: %1').replace('%1', config.baseLabel));

        editor = grapesjs.init({
            container: $root.find('[data-role=canvas]')[0],
            height: 'max(560px, calc(100vh - 260px))',
            // The admin must not report to grapesjs.com.
            telemetry: false,
            storageManager: false,
            fromElement: false,
            plugins: [mjmlPlugin, function (ed) {
                addBlockType(ed, config);
            }]
        });
        if (config.project) {
            editor.loadProjectData(config.project);
        } else {
            editor.setComponents(config.starter);
        }
        addMagentoBlocks(editor, config);
        addEmailBlocks(editor, config);
        addVariableAction(editor, config.variables);

        editor.on('update', function () {
            dirty = true;
        });
        editor.on('load', function () {
            dirty = false;
        });
        $name.add($subject).on('input', function () {
            dirty = true;
        });

        function renderVersions(versions) {
            $versions.empty().append($('<option>', {value: '', text: $t('Saved versions (%1)').replace('%1', versions.length)}));
            _.each(versions, function (version, index) {
                $versions.append($('<option>', {
                    value: version.version_id,
                    disabled: index === 0,
                    text: version.created_at + (version.username ? ' — ' + version.username : '')
                        + (index === 0 ? ' (' + $t('current') + ')' : '')
                }));
            });
            $versions.toggle(versions.length > 0);
        }
        renderVersions(config.versions || []);

        /** MJML → email HTML, with Magento directives kept intact. */
        function compile() {
            var mjml = directives.useStoreLogo(editor.runCommand('mjml-code')),
                guarded = directives.protect(mjml),
                result = editor.runCommand('mjml-code-to-html', {mjml: guarded.mjml});

            return {mjml: mjml, html: directives.restore(result.html || '', guarded.saved)};
        }

        function save() {
            var compiled = compile();

            return post(config.urls.save, {
                id: config.id || '',
                name: $name.val(),
                base: config.base,
                subject: $subject.val(),
                project: JSON.stringify(editor.getProjectData()),
                mjml: compiled.mjml,
                html: compiled.html
            }).then(function (response) {
                if (!response.success) {
                    fail(response.message);
                    return $.Deferred().reject();
                }
                config.id = response.id;
                dirty = false;
                renderVersions(response.versions);
                window.history.replaceState(null, '', response.editUrl);
                return response;
            }, function () {
                fail();
            });
        }

        function showPreview(rendered) {
            var $content = $('<div>'),
                $frame = $('<iframe>', {'class': 'ta-designer-preview-frame', sandbox: ''});

            $content.append($('<p>', {'class': 'ta-designer-preview-subject', text: rendered.subject}));
            $content.append($('<div>', {'class': 'ta-designer-preview-sizes'})
                .append($('<button type="button" class="action-default">').text($t('Desktop')).on('click', function () {
                    $frame.css('width', '100%');
                }))
                .append($('<button type="button" class="action-default">').text($t('Mobile')).on('click', function () {
                    $frame.css('width', '375px');
                })));
            $content.append($frame);
            $frame.attr('srcdoc', rendered.html);
            modal({
                title: $t('Preview with sample data'),
                type: 'slide',
                buttons: [],
                closed: function () {
                    $content.remove();
                }
            }, $content);
            $content.modal('openModal');
        }

        $root.find('[data-role=save]').on('click', save);

        $root.find('[data-role=preview]').on('click', function () {
            post(config.urls.preview, {html: compile().html, subject: $subject.val(), base: config.base})
                .done(function (response) {
                    response.success ? showPreview(response) : fail(response.message);
                })
                .fail(function () {
                    fail();
                });
        });

        $root.find('[data-role=send-test]').on('click', function () {
            prompt({
                title: $t('Send a test email'),
                content: $t('The saved design is sent with sample data. Unsaved changes are saved first.'),
                value: config.adminEmail,
                actions: {
                    confirm: function (email) {
                        var ready = dirty || !config.id ? save() : $.Deferred().resolve();

                        ready.then(function () {
                            return post(config.urls.sendTest, {id: config.id, email: email});
                        }).done(function (response) {
                            if (response) {
                                alert({title: $t('Email Designer'), content: _.escape(response.message)});
                            }
                        });
                    }
                }
            });
        });

        $versions.on('change', function () {
            var versionId = $versions.val();

            if (!versionId) {
                return;
            }
            confirm({
                title: $t('Restore this version?'),
                content: $t('It replaces the current design and is saved as the newest version. Unsaved changes are lost.'),
                actions: {
                    confirm: function () {
                        $('<form>', {method: 'post', action: config.urls.restore})
                            .append($('<input>', {type: 'hidden', name: 'form_key', value: window.FORM_KEY}))
                            .append($('<input>', {type: 'hidden', name: 'id', value: config.id}))
                            .append($('<input>', {type: 'hidden', name: 'version', value: versionId}))
                            .appendTo('body')
                            .trigger('submit');
                    },
                    always: function () {
                        $versions.val('');
                    }
                }
            });
        });

        $root.find('[data-role=back]').on('click', function (event) {
            event.preventDefault();
            if (!dirty) {
                window.location.href = config.urls.back;
                return;
            }
            confirm({
                content: $t('Leave without saving? Your changes will be lost.'),
                actions: {
                    confirm: function () {
                        window.location.href = config.urls.back;
                    }
                }
            });
        });

        $(window).on('beforeunload', function () {
            return dirty ? true : undefined;
        });

        return editor;
    };
});
