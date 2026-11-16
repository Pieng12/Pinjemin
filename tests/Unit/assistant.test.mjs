import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { matchQuestion } from '../../resources/js/assistant-matcher.js';

const config = JSON.parse(execFileSync('php', ['-r', "echo json_encode(require 'config/assistant.php');"], { encoding: 'utf8' }));
const topics = Object.entries(config.topics).filter(([, topic]) => !topic.admin).map(([id, topic]) => ({ id, ...topic }));

for (const [question, expected] of [
    ['  APA ITU PINJEMIN???  ', 'about'],
    ['DP', 'down-payment'],
    ['apakah dp itu biaya tambahan?', 'down-payment'],
    ['biaya kirim', 'shipping'],
    ['qris', 'payment'],
    ['bukti pembayaran ditolak', 'payment-review'],
    ['kalender merah', 'calendar'],
    ['mengapa tangal merah?', 'calendar'],
    ['cara menyewakan', 'list-item'],
    ['gimana saya tambah barang?', 'list-item'],
    ['barang rusak', 'damage'],
    ['bagaimana pencairan untuk pemilik?', 'payout'],
    ['saya ingin perpanjang sewa', 'late'],
    ['uang jaminan', 'down-payment'],
    ['komisi', 'commission'],
    ['batas waktu bayar', 'deadline'],
    ['syarat dan ketentuan', 'rules'],
]) {
    test(`answers the intended topic: ${question}`, () => {
        const result = matchQuestion(question, topics);
        assert.equal(result.kind, 'answer');
        assert.equal(result.topics[0].id, expected);
    });
}

test('asks for clarification when several topics are explicitly mentioned', () => {
    const result = matchQuestion('ongkir dan dp', topics);
    assert.equal(result.kind, 'choices');
    assert.deepEqual(new Set(result.topics.map(topic => topic.id)), new Set(['shipping', 'down-payment']));
});

for (const question of ['cuaca besok gimana', 'hitung 15 kali 20', 'produk palsu', 'administrator', 'simpan', '<img src=x onerror=alert(1)>']) {
    test(`does not fabricate an answer for: ${question}`, () => {
        assert.equal(matchQuestion(question, topics).kind, 'unknown');
    });
}

test('does not match a single generic keyword or a partial substring', () => {
    assert.equal(matchQuestion('barang', topics).kind, 'unknown');
    assert.equal(matchQuestion('pengirimannyaaa', topics).kind, 'unknown');
});

test('does not offer transaction help when only promotional topics are supplied', () => {
    const promotional = topics.filter(topic => topic.public);
    assert.equal(matchQuestion('bayar dp', promotional).kind, 'unknown');
    assert.equal(matchQuestion('halo', promotional).topics[0].id, 'about');
});

test('handles whitespace-only input without selecting a topic', () => {
    assert.deepEqual(matchQuestion('  \n  ', topics), { kind: 'empty', topics: [] });
});
