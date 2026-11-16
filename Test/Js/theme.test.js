/**
 * Run: node Test/Js/theme.test.js
 */
'use strict';

var assert = require('assert'),
    path = require('path'),
    theme = require(path.join(__dirname, '../../view/adminhtml/web/js/theme.js'));

/** A minimal stand-in for a GrapesJS component: get/set, style, children. */
function component(type, props, children) {
    var data = Object.assign({type: type, attributes: {}}, props),
        style = data.style || null,
        kids = children || [];

    delete data.style;
    return {
        get: function (key) { return data[key]; },
        set: function (key, value) { data[key] = value; },
        getStyle: function () { return style || {}; },
        setStyle: function (value) { style = value; },
        components: function (value) {
            if (value !== undefined) {
                kids = [component('textnode', {content: value})];
            }
            return kids;
        },
        data: data,
        style: function () { return style; }
    };
}

var DEFAULT = {primary: '#1979c3', background: '#f5f5f5', font: 'Helvetica, Arial, sans-serif', radius: '3px',
        footer: '{{var store.frontend_name}} · {{var store_email}}'},
    BRAND = {primary: '#aa0055', background: '#ffffff', font: 'Georgia, serif', radius: '12px',
        footer: 'Fashion & Co <shop> · {{var store_email}}'};

var link = component('link', {style: {color: '#1979C3'}}),
    blueButton = component('mj-button', {attributes: {'background-color': '#1979c3', 'border-radius': '3px'}}),
    greenButton = component('mj-button', {attributes: {'background-color': '#2e7d32', 'border-radius': '3px'}}),
    block = component('ta-block', {taDirective: '<td style="background:#f2f7fb;border-left:4px solid #1979c3">x</td>'}),
    footer = component('mj-text', {attributes: {'css-class': 'ta-footer', align: 'center'}},
        [component('textnode', {content: 'old'})]),
    text = component('mj-text', {}, [component('textnode', {content: 'Pay {{var total}}'}), link]),
    fonts = component('mj-all', {attributes: {'font-family': 'Helvetica, Arial, sans-serif'}}),
    body = component('mj-body', {attributes: {'background-color': '#F5F5F5'}},
        [fonts, text, blueButton, greenButton, block, footer]);

theme.apply(body, DEFAULT, BRAND);

assert.strictEqual(body.data.attributes['background-color'], '#ffffff', 'body background');
assert.strictEqual(fonts.data.attributes['font-family'], 'Georgia, serif', 'font');
assert.strictEqual(blueButton.data.attributes['background-color'], '#aa0055', 'brand button');
assert.strictEqual(blueButton.data.attributes['border-radius'], '12px', 'button radius');
assert.strictEqual(greenButton.data.attributes['background-color'], '#2e7d32', 'semantic colours stay');
assert.strictEqual(greenButton.data.attributes['border-radius'], '12px');
assert.strictEqual(link.style().color, '#aa0055', 'inline styles, any case');
assert.ok(block.data.taDirective.indexOf('solid #aa0055') !== -1, 'blocks');
assert.ok(block.data.taDirective.indexOf('#f2f7fb') !== -1, 'other colours in blocks stay');
assert.strictEqual(footer.components()[0].get('content'), 'Fashion &amp; Co &lt;shop&gt; · {{var store_email}}',
    'footer text, escaped, variables kept');
assert.strictEqual(text.components()[0].get('content'), 'Pay {{var total}}');

// Re-applying moves from the brand theme to the next one; applying the same theme changes nothing.
theme.apply(body, BRAND, DEFAULT);
assert.strictEqual(blueButton.data.attributes['background-color'], '#1979c3');
assert.strictEqual(body.data.attributes['background-color'], '#f5f5f5');
var before = JSON.stringify(blueButton.data);
theme.apply(body, DEFAULT, DEFAULT);
assert.strictEqual(JSON.stringify(blueButton.data), before);

assert.strictEqual(theme.swap('color:#1979C3', DEFAULT, BRAND), 'color:#aa0055');

console.log('theme: OK');
