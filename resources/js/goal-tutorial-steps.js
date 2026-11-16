import { tutorialSteps } from './tutorial-steps';
import { guideStage } from './guide-catalog';

export function goalTutorialSteps(config, goal = 'page') {
    const base = tutorialSteps(config);
    if (goal === 'page') return base;
    const stage = guideStage(config, goal);
    const heading = { selector: 'main h1', title: 'Ikuti panduan sesuai tujuan Anda', description: 'Sorotan menjelaskan kontrol pada bagian ini. Setelah selesai, gunakan formulir atau pilih data sendiri; panduan berlanjut pada bagian berikutnya.' };
    let content = base.filter(step => step.selector && step !== base[0] && !step.selector.startsWith('[data-workspace-') && !step.selector.startsWith('[data-mobile-'));
    if (stage === 'auth' || stage === 'create' || stage === 'identity') return [heading, ...content];
    if (stage === 'catalog') return [heading, ...content.filter(step => /#q|catalog-|#sort/.test(step.selector))];
    if (stage === 'item') return [heading, ...content];
    if (stage === 'bookings') return [heading, ...content.filter(step => /search|list-q|Pagination/.test(step.selector)), { selector: 'main a[href*="/my-bookings/"]', title: 'Pilih pemesanan yang ingin ditangani', description: 'Buka pemesanan terkait untuk melihat status dan langkah yang tersedia. Jika belum ada, Anda dapat mulai dari panduan menyewa.' }];
    if (stage === 'booking') {
        if (goal === 'return') return [heading, { selector: '[data-next-action]', title: 'Periksa langkah pengembalian', description: 'Periksa tanggal kembali serta tindakan yang tersedia. Pemesanan yang belum diserahkan mungkin belum memiliki tahap pengembalian.' }, { selector: '#booking-inspections-return', title: 'Pemeriksaan saat pengembalian', description: 'Ikuti kesiapan sesi, bukti, dan pemeriksaan kedua pihak. Penerimaan fisik dikonfirmasi pemilik atau diputuskan administrator berdasarkan bukti.' }, { selector: '#booking-history > summary', title: 'Riwayat pemesanan', description: 'Tinjau catatan yang tersedia pada pemesanan ini.' }];
        return [heading, ...content.filter(step => goal === 'payment' ? /next-action|payment-form|booking-delivery|booking-history/.test(step.selector) : !/workspace/.test(step.selector))];
    }
    if (stage === 'listing') content.push({ selector: 'main a[href$="/edit"]', title: 'Periksa atau edit informasi', description: 'Gunakan Edit jika ada informasi yang perlu dilengkapi. Setelah barang dipublikasikan, pantau pengajuan melalui Pemesanan Masuk.' });
    return [heading, ...content];
}
