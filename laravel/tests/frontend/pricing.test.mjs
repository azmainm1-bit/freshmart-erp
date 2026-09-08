import { test } from 'node:test';
import assert from 'node:assert/strict';
import { decimal, due, lineTotal, scaled } from '../../.test-build/pricing.js';

test('money is represented exactly in cents', () => {
    assert.equal(decimal(scaled('0.10',2)+scaled('0.20',2)), '0.30');
    assert.equal(decimal(scaled('999999999999.99',2)), '999999999999.99');
});
test('weighted quantities round once at the line boundary', () => {
    assert.equal(decimal(lineTotal('99.99','0.750','0',true)), '74.99');
    assert.equal(decimal(lineTotal('0.01','0.500','0',true)), '0.01');
});
test('exclusive tax follows a discount and inclusive tax is not added twice', () => {
    assert.equal(decimal(lineTotal('100','2','10',false,'20')), '198.00');
    assert.equal(decimal(lineTotal('100','2','10',true,'20')), '180.00');
});
test('returns settle credit and account for actual cash refunds', () => {
    assert.equal(due('200','100','75','0'), '25.00');
    assert.equal(due('200','200','75','75'), '0.00');
    assert.equal(due('100','10','100'), '-10.00');
});
