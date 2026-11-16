import test from 'node:test';
import assert from 'node:assert/strict';
import { tutorialSteps } from '../../resources/js/tutorial-steps.js';

test('catalog tutorial follows search, filters, order and item detail', () => {
    const steps = tutorialSteps({ page: 'browse' });
    const titles = steps.map(step => step.title);
    assert(titles.indexOf('1. Cari barang') < titles.indexOf('2. Buka filter'));
    assert(titles.indexOf('2. Buka filter') < titles.indexOf('5. Baca detail barang'));
    assert(steps.some(step => step.selector === '.catalog-filter-panel'));
});

test('rental tutorial explains inclusive dates, requirements and applying', () => {
    const steps = tutorialSteps({ page: 'items.show', context: 'renter' });
    assert(steps.some(step => /inklusif/.test(step.description)));
    assert(steps.some(step => step.selector?.includes('requirements_accepted')));
    assert(steps.some(step => step.title === '7. Kirim pengajuan'));
});

test('authentication tutorial includes the password eye and explicit agreement', () => {
    const steps = tutorialSteps({ page: 'register' });
    assert(steps.some(step => step.selector === '[data-password-toggle]'));
    assert(steps.some(step => step.selector?.includes('terms_accepted')));
});

test('promotional tutorial has no transaction destinations and explains Instagram', () => {
    const steps = tutorialSteps({ page: 'home', context: 'promotion', marketplaceEnabled: false });
    assert(!steps.some(step => step.destination));
    assert(steps.some(step => /Instagram resmi/.test(step.description)));
    assert(!steps.some(step => /Pembayaran|Kirim pengajuan/.test(step.title)));
});

test('owner tutorial preserves the meaning of DP and photo requirements', () => {
    const steps = tutorialSteps({ page: 'my-items.edit', context: 'owner' });
    assert(steps.some(step => /bukan uang jaminan/.test(step.description)));
    assert(steps.some(step => step.selector?.includes('requires_condition_photos')));
    assert(steps.some(step => step.destination === 'incoming'));
});

test('booking tutorial follows the available next action and privacy of payment details', () => {
    const steps = tutorialSteps({ page: 'my-bookings.show', context: 'renter' });
    assert(steps.some(step => step.selector === '[data-next-action]'));
    assert(steps.some(step => /diperiksa admin/.test(step.description)));
    assert(steps.some(step => step.selector === '#booking-history > summary'));
});
