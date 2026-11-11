/**
 * Run: node Test/Js/directives.test.js [path/to/mjml-browser]
 * With the optional argument it also compiles through real MJML (e.g. node_modules/mjml-browser).
 */
'use strict';

var assert = require('assert'),
    path = require('path'),
    directives = require(path.join(__dirname, '../../view/adminhtml/web/js/directives.js'));

var source = '<mjml><mj-body><mj-section><mj-column>'
    + '<mj-image css-class="ta-store-logo" src="https://shop.test/logo.png" alt="{{var logo_alt}}"></mj-image>'
    + '<mj-image src="https://shop.test/banner.png"></mj-image>'
    + '<mj-text>Hi {{var customer.name}}, {{trans "Thanks %name" name=$customer.name}}</mj-text>'
    + '<mj-button href="{{store url=&quot;customer/account/&quot;}}">Account</mj-button>'
    + '<mj-raw>{{depend order.coupon}}<p>Coupon</p>{{/depend}}</mj-raw>'
    + '</mj-column></mj-section></mj-body></mjml>';

var withLogo = directives.useStoreLogo(source);
assert.ok(withLogo.indexOf('src="{{var logo_url}}"') !== -1, 'the logo block gets the logo variable');
assert.ok(withLogo.indexOf('banner.png') !== -1, 'other images keep their source');

var protectedMjml = directives.protect(withLogo);
assert.strictEqual(protectedMjml.mjml.indexOf('{{'), -1, 'no directive reaches the compiler');
assert.strictEqual(protectedMjml.saved.length, 7);
assert.strictEqual(protectedMjml.saved[4], '{{store url="customer/account/"}}', 'escaped quotes are decoded');

var restored = directives.restore(protectedMjml.mjml, protectedMjml.saved);
assert.strictEqual(restored, withLogo.replace('&quot;customer/account/&quot;', '"customer/account/"'));

if (process.argv[2]) {
    global.window = global;
    var mjml = require(path.resolve(process.argv[2])),
        result = mjml(protectedMjml.mjml, {validationLevel: 'strict'}),
        html = directives.restore(result.html, protectedMjml.saved);

    assert.deepStrictEqual(result.errors, []);
    protectedMjml.saved.forEach(function (directive) {
        assert.ok(html.indexOf(directive) !== -1, 'compiled HTML keeps ' + directive);
    });
    assert.ok(html.indexOf('href="{{store url="customer/account/"}}"') !== -1);
}

console.log('directives: OK');
