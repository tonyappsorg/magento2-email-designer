/**
 * Keeps Magento template directives ({{var ...}}, {{trans "..."}}, {{store url="..."}}) intact through the MJML
 * compiler, which breaks attribute values that contain quotes. Each directive is swapped for a plain token before
 * compiling and put back afterwards.
 */
(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else {
        module.exports = factory();
    }
}(function () {
    'use strict';

    var TOKEN = 'TADIRECTIVE',
        LOGO_CLASS = 'ta-store-logo';

    // The editor exports attribute values HTML-escaped; the directive itself must reach Magento unescaped.
    function decode(text) {
        return text
            .replace(/&quot;/g, '"')
            .replace(/&#0?39;|&apos;/g, '\'')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&');
    }

    return {
        LOGO_CLASS: LOGO_CLASS,

        /** The logo block shows the real logo in the editor; the email gets the store's logo variable. */
        useStoreLogo: function (mjml) {
            return mjml.replace(/<mj-image\b[^>]*>/g, function (tag) {
                return tag.indexOf(LOGO_CLASS) === -1 ? tag : tag.replace(/\ssrc="[^"]*"/, ' src="{{var logo_url}}"');
            });
        },

        protect: function (mjml) {
            var saved = [];

            return {
                mjml: mjml.replace(/\{\{[\s\S]*?\}\}/g, function (directive) {
                    saved.push(decode(directive));
                    return TOKEN + (saved.length - 1) + 'X';
                }),
                saved: saved
            };
        },

        restore: function (html, saved) {
            return html.replace(new RegExp(TOKEN + '(\\d+)X', 'g'), function (token, index) {
                return saved[Number(index)];
            });
        }
    };
}));
