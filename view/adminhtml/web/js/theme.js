/**
 * Brand theme: swaps the values of one theme for another in a GrapesJS component tree. A design remembers the theme
 * it last got, so re-applying a theme changes exactly those values and leaves the rest of the design (semantic
 * colours such as a green "approved" button) alone.
 *
 * Theme: {primary, background, font, radius, footer}. Starter designs and module blocks are written in the
 * default theme, so they follow the store's theme the same way.
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

    var FOOTER_CLASS = 'ta-footer';

    function escapeRegExp(text) {
        return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /** The primary colour (any case) and the font stack, wherever they appear in a value. */
    function swap(value, from, to) {
        if (typeof value !== 'string' || !value) {
            return value;
        }
        if (from.primary && to.primary && from.primary.toLowerCase() !== to.primary.toLowerCase()) {
            value = value.replace(new RegExp(escapeRegExp(from.primary), 'gi'), to.primary);
        }
        if (from.font && to.font && from.font !== to.font) {
            value = value.split(from.font).join(to.font);
        }
        return value;
    }

    function swapObject(object, from, to) {
        var result = {},
            changed = false;

        Object.keys(object || {}).forEach(function (key) {
            result[key] = swap(object[key], from, to);
            changed = changed || result[key] !== object[key];
        });
        return changed ? result : null;
    }

    function same(a, b) {
        return String(a || '').toLowerCase() === String(b || '').toLowerCase();
    }

    function apply(component, from, to) {
        var type = component.get('type'),
            attributes = component.get('attributes') || {},
            swapped = swapObject(attributes, from, to) || Object.assign({}, attributes),
            style = component.getStyle ? component.getStyle() : null,
            swappedStyle = style && swapObject(style, from, to);

        if (type === 'mj-body' && same(attributes['background-color'], from.background)) {
            swapped['background-color'] = to.background;
        }
        if (type === 'mj-button' && same(attributes['border-radius'], from.radius)) {
            swapped['border-radius'] = to.radius;
        }
        if (JSON.stringify(swapped) !== JSON.stringify(attributes)) {
            component.set('attributes', swapped);
        }
        if (swappedStyle) {
            component.setStyle(swappedStyle);
        }
        ['content', 'taDirective'].forEach(function (property) {
            var value = component.get(property),
                next = swap(value, from, to);

            if (next !== value) {
                component.set(property, next);
            }
        });

        if ((' ' + (attributes['css-class'] || '') + ' ').indexOf(' ' + FOOTER_CLASS + ' ') !== -1) {
            component.components(escapeHtml(to.footer));
            return;
        }
        component.components().forEach(function (child) {
            apply(child, from, to);
        });
    }

    return {
        FOOTER_CLASS: FOOTER_CLASS,
        swap: swap,

        /** Applies theme `to` over theme `from` to the component and everything inside it. */
        apply: function (component, from, to) {
            if (component && from && to) {
                apply(component, from, to);
            }
        }
    };
}));
