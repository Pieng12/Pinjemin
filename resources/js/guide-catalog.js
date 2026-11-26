export function availableGuides(config) {
    if (!config.marketplaceEnabled) return [];
    const guides = [
        { id: 'rent', title: 'Panduan Penyewaan', description: 'Dari mencari barang sampai mengajukan dan membayar pemesanan.', entry: 'browse', entryLabel: 'katalog', steps: [
            ['auth', 'Siapkan akun', 'Masuk atau daftar agar dapat mengajukan penyewaan.'],
            ['catalog', 'Cari dan pilih barang', 'Gunakan filter periode dan jumlah unit untuk mencari barang yang tersedia pada tanggal yang Anda perlukan.'],
            ['item', 'Tentukan periode dan ajukan', 'Baca aturan, pilih tanggal, jumlah, serta cara menerima barang.'],
            ['booking', 'Pantau dan bayar pemesanan', 'Tunggu pemilik, sepakati biaya pengiriman bila ada, lalu bayar sesuai instruksi.'],
            ['return', 'Terima dan kembalikan', 'Ikuti pemeriksaan kondisi serta tanggal pengembalian pada pemesanan.'],
        ] },
        { id: 'list', title: 'Panduan Pemilik Barang', description: 'Tambahkan barang, atur persyaratan, dan kelola permintaan sewa.', entry: 'create-item', entryLabel: 'Tambah Barang', steps: [
            ['auth', 'Siapkan akun pemilik', 'Satu akun dapat menyewa sekaligus menyewakan barang.'],
            ['create', 'Lengkapi informasi barang', 'Siapkan foto asli, harga, stok, lokasi, dan persyaratan.'],
            ['listing', 'Periksa barang yang tersimpan', 'Pastikan informasi dan publikasi sesuai sebelum menerima penyewa.'],
            ['incoming', 'Tinjau pemesanan masuk', 'Periksa pengajuan, biaya pengiriman, dan persyaratan sebelum menerima.'],
            ['payout', 'Selesaikan sewa dan pencairan', 'Ikuti serah terima serta pengembalian, dan lengkapi tujuan pencairan.'],
        ] },
        { id: 'payment', title: 'Pembayaran dan Uang Muka', description: 'Temukan pemesanan, periksa nominal, lalu kirim bukti pembayaran.', entry: 'my-bookings', entryLabel: 'Penyewaan Saya', steps: [
            ['auth', 'Masuk ke akun penyewa', 'Pembayaran tersedia pada pemesanan milik Anda.'],
            ['bookings', 'Pilih pemesanan', 'Buka pemesanan yang sudah diterima dan totalnya disepakati.'],
            ['booking', 'Periksa instruksi dan bayar', 'Pilih tujuan yang tercatat dan nominal lunas atau DP yang diizinkan. DP bagian dari total.'],
            ['review', 'Unggah bukti dan pantau', 'Isi waktu transaksi serta bukti asli. Pembayaran platform diperiksa administrator secara manual.'],
        ] },
        { id: 'return', title: 'Pengembalian barang', description: 'Lihat tanggal kembali dan ikuti pemeriksaan kondisi kedua pihak.', entry: 'my-bookings', entryLabel: 'Penyewaan Saya', steps: [
            ['auth', 'Masuk ke akun penyewa', 'Gunakan akun yang mengajukan penyewaan.'],
            ['bookings', 'Buka pemesanan terkait', 'Periksa tanggal pengembalian dan aksesori yang harus dikembalikan.'],
            ['booking', 'Periksa kondisi bersama', 'Ikuti sesi, foto, daftar pemeriksaan, dan persetujuan sesuai persyaratan pemesanan.'],
            ['complete', 'Tunggu konfirmasi penerimaan', 'Pengiriman balik belum berarti diterima. Pemilik mengonfirmasi penerimaan fisik dan pemeriksaan diselesaikan.'],
        ] },
        { id: 'identity', title: 'Verifikasi identitas', description: 'Kenali pengisian dokumen dan persetujuan penggunaan datanya.', entry: 'identity', entryLabel: 'Verifikasi Identitas', steps: [
            ['auth', 'Masuk ke akun Anda', 'Dokumen identitas hanya diajukan melalui area akun.'],
            ['identity', 'Isi dan unggah dokumen', 'Pilih KTP atau SIM, lengkapi data, dan periksa masa berlaku bila diperlukan.'],
            ['review', 'Pantau pemeriksaan administrator', 'Baca alasan perbaikan jika ada. Jangan mengirim dokumen melalui layanan bantuan ini.'],
        ] },
    ];
    if (config.chatEnabled) guides.push({ id: 'item-chat', title: 'Chat Barang', description: 'Hubungi pihak terkait, gunakan Pesan Cepat, dan kirim lampiran.', entry: 'browse', entryLabel: 'katalog', steps: [
        ['catalog', 'Pilih barang di katalog', 'Tekan Hubungi Pemilik pada barang yang ingin ditanyakan, lalu masuk ke akun jika diminta.'],
        ['item', 'Mulai percakapan', 'Periksa kartu barang. Pilih Pesan Cepat atau tulis pesan sendiri. Ikon penjepit kertas digunakan untuk menambahkan lampiran.'],
        ['inbox', 'Buka kembali percakapan', 'Pilih Chat di menu akun, lalu cari percakapan berdasarkan nama barang.'],
    ] });
    if (config.navigation?.['admin-finance']) guides.push({ id: 'admin', title: 'Kelola transaksi administrator', description: 'Kenali pemeriksaan pembayaran, pengembalian dana, dan pencairan.', entry: 'admin-finance', entryLabel: 'Keuangan Administrator', steps: [
        ['queue', 'Pilih antrean', 'Gunakan tab atau filter pembayaran, pengembalian dana, dan pencairan.'],
        ['booking', 'Tinjau pemesanan dan bukti', 'Buka transaksi untuk memeriksa catatan dan tindakan yang diizinkan.'],
        ['review', 'Catat keputusan', 'Gunakan formulir yang tersedia sesuai kewenangan dan bukti.'],
    ] });
    return guides.filter(guide => config.navigation?.[guide.entry]);
}

export function guideStage(config, id) {
    const page = config.page || '';
    if (['login', 'register'].includes(page)) return 'auth';
    if (id === 'rent') {
        if (page === 'browse') return 'catalog';
        if (page === 'items.show') return 'item';
        if (page === 'my-bookings.index') return 'bookings';
        if (page === 'my-bookings.show') return 'booking';
    }
    if (id === 'item-chat') {
        if (page === 'browse') return 'catalog';
        if (page === 'items.show') return 'item';
    }
    if (id === 'list') {
        if (['my-items.create', 'my-items.edit'].includes(page)) return 'create';
        if (['my-items.show', 'my-items.index', 'rent-out', 'owner.dashboard'].includes(page)) return 'listing';
        if (page.startsWith('incoming-bookings.')) return 'incoming';
        if (['owner.payouts', 'payment-methods.index'].includes(page)) return 'payout';
    }
    if (['payment', 'return'].includes(id)) {
        if (page === 'my-bookings.index') return 'bookings';
        if (page === 'my-bookings.show') return 'booking';
    }
    if (id === 'identity' && page.startsWith('identity.')) return page === 'identity.index' ? 'identity' : 'review';
    if (id === 'admin') {
        if (page === 'admin.finance') return 'queue';
        if (page === 'admin.bookings.show') return 'booking';
    }
    return null;
}

export function guideInstruction(id, stage) {
    if (stage === 'auth') return { text: 'Lengkapi formulir akun, kemudian kirim untuk melanjutkan. Panduan berlanjut setelah Anda berhasil masuk.', label: 'Isi formulir akun', selector: 'main input[name="email"]' };
    if (id === 'item-chat' && ['catalog', 'item'].includes(stage)) return { text: 'Pilih Hubungi Pemilik pada barang yang ingin Anda tanyakan. Kartu barang dan pilihan Pesan Cepat muncul setelah percakapan dibuka.', label: 'Temukan tombol Hubungi Pemilik', selector: 'main a[href$="/pesan"]' };
    if (stage === 'catalog') return { text: 'Pilih barang yang akan disewa. Sorotan berikutnya muncul setelah Anda membuka detail barang.', label: 'Pilih barang di katalog', selector: '.catalog-card a, #q' };
    if (stage === 'item') return { text: 'Isi periode, jumlah, dan penerimaan barang, lalu ajukan jika sudah sesuai. Panduan berlanjut pada detail pemesanan.', label: 'Isi pilihan sewa', selector: '[data-booking-range], [data-booking-form] [name="quantity"]' };
    if (stage === 'create') return { text: 'Lengkapi informasi barang, kemudian simpan perubahan. Panduan berlanjut pada barang yang Anda simpan.', label: 'Isi informasi barang', selector: '[data-item-form-fields] input[name="name"]' };
    if (stage === 'bookings') return { text: 'Pilih pemesanan yang ingin ditangani. Jika belum ada pemesanan, mulai dari panduan menyewa terlebih dahulu.', label: 'Pilih pemesanan', selector: 'main a[href*="/my-bookings/"]' };
    if (stage === 'booking' && id === 'payment') return { text: 'Pembayaran tersedia setelah pemesanan diterima dan total disepakati. Baca Langkah Selanjutnya jika masih menunggu; bukti yang dikirim belum berarti lunas.', label: 'Periksa langkah pemesanan', selector: '[data-next-action]' };
    if (stage === 'booking' && id === 'return') return { text: 'Ikuti tindakan pengembalian yang sudah tersedia. Penerimaan fisik dan pemeriksaan kondisi tetap memerlukan konfirmasi sesuai peran.', label: 'Lihat pemeriksaan pemesanan', selector: '#booking-inspections-return, [data-next-action]' };
    if (stage === 'booking') return { text: 'Ikuti Langkah Selanjutnya pada pemesanan. Saat menunggu persetujuan, pembayaran atau penyerahan mungkin belum tersedia.', label: 'Lihat langkah pemesanan', selector: '[data-next-action]' };
    if (stage === 'identity') return { text: 'Isi dokumen melalui formulir privat dan baca persetujuan. Jangan memasukkan nomor identitas atau gambar dokumen ke percakapan.', label: 'Isi verifikasi identitas', selector: '[name="document_type"]' };
    return { text: 'Gunakan tindakan yang tersedia pada halaman ini sesuai status dan peran Anda. Panduan dapat disorot ulang kapan saja.', label: 'Lanjutkan pada halaman ini', selector: 'main h1' };
}
