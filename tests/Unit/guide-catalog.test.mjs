import test from 'node:test';
import assert from 'node:assert/strict';
import { availableGuides, guideStage, guideInstruction } from '../../resources/js/guide-catalog.js';

const navigation = Object.fromEntries(['browse', 'create-item', 'my-bookings', 'identity'].map(key => [key, { url: 'https://pinjemin.test/' + key }]));

test('offers goal-based rental, owner, payment, return and identity choices', () => {
    assert.deepEqual(availableGuides({ marketplaceEnabled: true, navigation }).map(guide => guide.id), ['rent', 'list', 'payment', 'return', 'identity']);
});
test('only offers the admin guide when the server includes an admin destination', () => {
    assert(!availableGuides({ marketplaceEnabled: true, navigation }).some(guide => guide.id === 'admin'));
    assert(availableGuides({ marketplaceEnabled: true, navigation: { ...navigation, 'admin-finance': { url: '/admin/finance' } } }).some(guide => guide.id === 'admin'));
});
test('does not offer transaction journeys in promotion mode', () => {
    assert.deepEqual(availableGuides({ marketplaceEnabled: false, navigation }), []);
});
test('rental guide follows account, catalog, actual item and actual booking pages', () => {
    for (const [page, phase] of [['login', 'auth'], ['register', 'auth'], ['browse', 'catalog'], ['items.show', 'item'], ['my-bookings.show', 'booking']]) {
        assert.equal(guideStage({ page }, 'rent'), phase);
    }
    assert.equal(guideStage({ page: 'profile.edit' }, 'rent'), null);
    assert.equal(guideStage({ page: 'incoming-bookings.show' }, 'rent'), null);
});
test('owner guide follows the saved item and incoming requests', () => {
    assert.equal(guideStage({ page: 'my-items.create' }, 'list'), 'create');
    assert.equal(guideStage({ page: 'my-items.show' }, 'list'), 'listing');
    assert.equal(guideStage({ page: 'incoming-bookings.index' }, 'list'), 'incoming');
});
test('instructions require the user to choose an item and fill rental information', () => {
    assert.match(guideInstruction('rent', 'catalog').text, /Pilih barang yang akan disewa/);
    assert.match(guideInstruction('rent', 'item').text, /Isi periode, jumlah/);
});
test('payment and return instructions do not claim a booking is paid or physically returned', () => {
    assert.match(guideInstruction('payment', 'booking').text, /bukti yang dikirim belum berarti lunas/);
    assert.match(guideInstruction('return', 'booking').text, /memerlukan konfirmasi sesuai peran/);
});
