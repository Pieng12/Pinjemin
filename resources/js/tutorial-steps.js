const step = (selector, title, description) => ({ selector, title, description });
const link = (destination, title, description) => ({ destination, title, description });

export function tutorialSteps({ page = '', context = 'guest', marketplaceEnabled = true, chatEnabled = false }) {
    const navigation = [
        step('[data-mobile-menu-toggle]', 'Menu navigasi', 'Tombol ini membuka menu halaman dan akses akun pada layar yang lebih kecil.'),
        step('[data-workspace-open]', 'Menu dasbor', 'Buka menu ini untuk berpindah bagian dasbor. Tutorial juga akan menunjukkan menu yang tersedia.'),
        step('[data-workspace-collapse]', 'Ciutkan atau buka sidebar', 'Gunakan tombol ini untuk mengubah lebar sidebar. Pilihan sidebar disimpan untuk area administrator dan pemilik secara terpisah.'),
    ];
    const heading = step('main h1, .home-hero h1', 'Mulai dari halaman ini', 'Tutorial ini menjelaskan kontrol yang tersedia di halaman yang sedang Anda buka. Ulangi dari Pusat Bantuan Pinjemin setelah berpindah halaman.');
    const lists = [
        step('[role="search"] input[type="search"], input#list-q', 'Cari dalam daftar', 'Ketik nama atau kata kunci yang relevan, lalu pilih Terapkan. Pencarian tetap mengikuti area dan hak akses akun Anda.'),
        step('[role="search"] select', 'Saring hasil', 'Pilih status, kategori, atau peran yang tersedia untuk mempersempit hasil, lalu pilih Terapkan. Reset menghapus penyaringan.'),
        step('main nav[aria-label="Pagination Navigation"], main nav[aria-label="Pagination"]', 'Halaman berikutnya', 'Gunakan pagination untuk membuka hasil berikutnya. Pencarian dan filter tetap dibawa saat berpindah halaman.'),
    ];
    let content = [];
    if (page === 'home') {
        content = [
            step('.home-action', marketplaceEnabled ? 'Mulai menyewa barang' : 'Terhubung dengan Pinjemin', marketplaceEnabled ? 'Pilih Menyewa Barang untuk membuka katalog. Dari sana Anda dapat mencari barang, membaca detail, lalu menentukan periode sewa.' : 'Tombol ini membuka Instagram resmi Pinjemin untuk mengenal informasi dan kontennya.'),
            step('.home-hero-copy .btn-secondary', marketplaceEnabled ? 'Pendaftaran barang sewaan' : 'Tentang Pinjemin', marketplaceEnabled ? 'Pilih Sewakan Barang untuk mulai sebagai pemilik. Setelah masuk, Anda dapat menambahkan barang dan mengelola pengajuan.' : 'Tombol ini mengarah ke pengenalan Pinjemin pada halaman yang sama.'),
            step('#cara-kerja h2', 'Pahami urutan penyewaan', 'Bagian ini menjelaskan alur dari mencari barang, mengajukan dan membayar, menerima, sampai mengembalikan barang.'),
            step('#faq summary', 'Buka pertanyaan umum', 'Pilih pertanyaan untuk membaca jawabannya. Bantuan lebih lengkap tersedia di Pusat Bantuan Pinjemin.'),
        ];
    } else if (page === 'browse') {
        content = [
            step('#q', '1. Cari barang', 'Masukkan barang yang dibutuhkan, seperti kamera atau tenda, lalu gunakan tombol Cari.'),
            step('.catalog-filter-toggle', '2. Buka filter', 'Saring barang berdasarkan kategori, kota, kondisi, harga, atau periode sewa.'),
            step('.catalog-filter-panel', '3. Periksa periode sewa', 'Isi kedua tanggal dan jumlah unit yang diperlukan, lalu pilih Terapkan. Pilihan periode terbawa ke detail barang.'),
            step('#sort', '4. Urutkan hasil', 'Pilih urutan terbaru, harga termurah, atau termahal. Gunakan tombol panah di sampingnya untuk menerapkan urutan.'),
            step('.catalog-card a', '5. Baca detail barang', 'Buka kartu untuk melihat foto, kondisi, aturan, harga, dan lokasi. Di halaman detail Anda dapat memilih tanggal serta jumlah unit.'),
            ...lists.slice(2),
        ];
    } else if (page === 'items.show') {
        content = [
            step('[data-gallery]', '1. Periksa foto barang', 'Lihat foto dan kelengkapan barang. Gunakan foto kecil di bawah galeri jika tersedia untuk mengganti tampilan utama.'),
            ...(chatEnabled ? [step('main a[href$="/pesan"]', 'Hubungi pemilik', 'Percakapan menampilkan kartu barang dengan tarif katalog. Gunakan Pesan Cepat atau tulis pertanyaan Anda sendiri.')] : []),
            step('[data-booking-range], [name="start_date"]', '2. Tentukan periode sewa', 'Pilih tanggal mulai dan akhir. Rentang dihitung inklusif; tanggal merah tidak dapat dipilih jika sisa stok tidak mencukupi.'),
            step('[data-booking-form] [name="quantity"]', '3. Tentukan jumlah unit', 'Isi jumlah yang diperlukan. Ketersediaan tanggal dan estimasi biaya mengikuti jumlah unit ini.'),
            step('[data-booking-form] [name="fulfillment_method"]', '4. Pilih cara menerima barang', 'Ambil sendiri tersedia. Pilihan dikirim muncul jika diizinkan pemilik; alamat penerima harus diperiksa saat memilih pengiriman.'),
            step('[data-booking-form] [data-subtotal]', '5. Periksa estimasi biaya', 'Pastikan periode dan jumlah sudah sesuai. Untuk pemesanan baru DP merupakan bagian dari biaya, sedangkan biaya pengiriman mengikuti pilihan pengiriman.'),
            step('[name="requirements_accepted"]', '6. Baca dan setujui persyaratan', 'Periksa aturan barang, kewajiban foto, dan verifikasi identitas sebelum menyetujui. Persyaratan yang disepakati disimpan pada pemesanan.'),
            step('[data-booking-form] button[type="submit"]', '7. Kirim pengajuan', 'Setelah data lengkap, gunakan tombol ini untuk mengajukan pemesanan. Pantau tanggapan pemilik dan pembayaran pada Penyewaan Saya.'),
            link('login', 'Masuk untuk mengajukan pemesanan', 'Masuk terlebih dahulu untuk memilih periode dan mengajukan penyewaan. Setelah itu lanjutkan panduan di halaman detail barang.'),
        ];
    } else if (page === 'login' || page === 'register') {
        content = [
            step('main input[name="name"]', 'Nama akun', 'Gunakan nama yang benar agar pihak pemesanan dapat mengenali akun Anda.'),
            step('main input[name="email"]', 'Email akun', 'Gunakan email akun untuk masuk, atau email yang ingin didaftarkan untuk akun baru.'),
            step('main input[name="password"]', 'Kata Sandi', 'Masukkan kata sandi sesuai petunjuk formulir. Jangan membagikannya melalui percakapan atau kepada pengguna lain.'),
            step('[data-password-toggle]', 'Tampilkan atau sembunyikan kata sandi', 'Tombol mata membantu memeriksa kata sandi yang diketik. Tekan lagi untuk menyembunyikannya.'),
            step('input[name="password_confirmation"]', 'Konfirmasi kata sandi', 'Ketik ulang kata sandi yang sama untuk memastikan tidak ada kesalahan pengetikan.'),
            step('input[name="terms_accepted"]', 'Persetujuan aturan', 'Baca Syarat & Ketentuan dan Kebijakan Privasi melalui tautan di dekat kotak persetujuan. Centang setelah Anda menyetujuinya.'),
            step('main form button[type="submit"]', page === 'login' ? 'Masuk ke akun' : 'Daftarkan akun', 'Setelah seluruh data benar dan persetujuan diberikan, gunakan tombol ini untuk melanjutkan.'),
        ];
    } else if (/^my-items\.(create|edit)$/.test(page)) {
        content = [
            step('[name="name"]', '1. Informasi barang', 'Isi nama yang jelas, pilih kategori, lalu jelaskan kondisi dan kelengkapannya dengan jujur.'),
            step('#daily_price_display, [name="daily_price"]', '2. Harga harian', 'Tetapkan biaya sewa per unit per hari. Harga ini digunakan untuk menghitung subtotal sesuai periode dan jumlah unit.'),
            step('#deposit_amount_display, [name="deposit_amount"]', '3. Pengaturan DP', 'DP adalah bagian dari total, bukan uang jaminan atau biaya tambahan. Pilihan pembayaran mengikuti pengaturan yang tersedia pada barang.'),
            step('[name="quantity"]', '4. Stok barang', 'Jumlah unit menentukan ketersediaan kalender. Sesuaikan stok dengan unit yang benar-benar dapat disewakan.'),
            step('[name="address"]', '5. Lokasi pengambilan', 'Lengkapi lokasi pengambilan dan kota. Alamat lengkap ditampilkan sesuai akses dan tahap pemesanan yang berlaku.'),
            step('[name="delivery_enabled"]', '6. Pilihan pengiriman', 'Aktifkan hanya jika Anda bersedia mengirimkan barang. Pengambilan langsung tetap tersedia.'),
            step('[data-photo-upload]', '7. Foto katalog', 'Unggah foto asli yang memperlihatkan barang dengan jelas. Periksa pratinjau dan kelengkapan foto sebelum menyimpan.'),
            step('[name="requires_condition_photos"]', '8. Persyaratan foto kondisi', 'Jika diwajibkan, kedua pihak harus mendokumentasikan kondisi saat penyerahan dan pengembalian untuk pemesanan baru.'),
            step('[name="requires_identity_verification"]', '9. Persyaratan identitas', 'Jika diaktifkan, penyewa mengikuti verifikasi identitas melalui administrator sebelum pengajuan dapat diterima.'),
            step('[name="is_active"]', '10. Publikasi barang', 'Barang yang diaktifkan mengikuti kelengkapan dan validasi publikasi. Periksa kembali seluruh informasi sebelum menyimpan.'),
        ];
    } else if (/^(my-bookings|incoming-bookings|admin.bookings)\.show$/.test(page)) {
        content = [
            step('[data-next-action]', '1. Lihat langkah selanjutnya', 'Panel ini menunjukkan tindakan yang sesuai dengan peran, status, dan persyaratan pemesanan. Baca juga siapa yang sedang ditunggu.'),
            step('#booking-delivery', '2. Konfirmasi biaya pengiriman bila diperlukan', 'Tawaran biaya pengiriman perlu ditinjau penyewa sebelum masuk ke pembayaran. Perhatikan total dan batas konfirmasi dalam WIB.'),
            step('[data-payment-form] [name="payment_plan"]', '3. Pilih strategi pembayaran', 'Gunakan pilihan lunas atau DP yang tersedia. Nominal pembayaran mengikuti total dan pembayaran yang sudah terverifikasi.'),
            step('[data-payment-form] [name="method_index"]', '4. Pilih tujuan pembayaran', 'Periksa rekening atau QRIS dan instruksinya pada pemesanan. Pembayaran baru menuju Pinjemin dan diperiksa administrator.'),
            step('[data-payment-form] [data-private-image-input]', '5. Unggah bukti transfer', 'Masukkan waktu transaksi dan foto bukti yang jelas setelah transfer. Status diperiksa belum berarti pembayaran disetujui.'),
            step('[data-inspection-panel]', '6. Periksa kondisi barang', 'Ikuti kesiapan sesi, daftar pemeriksaan, bukti masing-masing pihak, serta persetujuan atau keberatan yang tersedia pada tahap ini.'),
            step('#booking-payout', '7. Pantau pengembalian dana atau pencairan', 'Tindakan keuangan mengikuti status dan hak akses. Pencairan tetap menunggu kelengkapan pengembalian dan pemeriksaan.'),
            step('#booking-history > summary', '8. Riwayat dan dokumen', 'Buka bagian ini untuk meninjau perjalanan pemesanan dan catatan terdahulu. Gunakan tombol unduhan yang tersedia sesuai status.'),
        ];
    } else if (page === 'profile.edit') {
        content = [
            step('[data-profile-photo-upload]', 'Foto profil', 'Pilih foto yang diinginkan dan periksa pratinjau sebelum menyimpan. Foto membantu pengguna mengenali akun Anda.'),
            step('main [name="name"]', 'Informasi akun', 'Perbarui nama dan informasi kontak dengan benar. Email mengikuti ketentuan akun yang tersedia.'),
            step('main [name="phone"]', 'Telepon yang dapat dihubungi', 'Lengkapi nomor yang benar agar informasi penerima dan komunikasi transaksi dapat diperiksa.'),
            step('main [name="address"]', 'Alamat profil', 'Alamat dapat membantu mengisi pengiriman. Periksa kembali alamat penerima pada setiap pengajuan.'),
            step('form:has([name="name"]) button[type="submit"]', 'Simpan perubahan', 'Gunakan tombol ini setelah memeriksa perubahan. Tutorial tidak mengubah isian formulir.'),
            link('identity', 'Verifikasi identitas', 'Lengkapi pemeriksaan identitas melalui halaman ini jika disyaratkan barang yang ingin disewa.'),
        ];
    } else if (page.startsWith('identity.')) {
        content = [
            step('[name="document_type"]', 'Pilih dokumen', 'Gunakan KTP atau SIM sesuai dokumen yang dimiliki. Pemeriksaan dilakukan manual oleh administrator berwenang.'),
            step('[name="document_number"]', 'Nomor identitas', 'Isi sesuai dokumen melalui formulir privat ini. Jangan memasukkannya ke percakapan bantuan.'),
            step('[name="document_expires_on"]', 'Masa berlaku', 'Untuk SIM, tanggal kedaluwarsa wajib diisi. Hasil pemeriksaan berlaku maksimal satu tahun atau sampai dokumen berakhir jika lebih cepat.'),
            step('main input[type="file"]', 'Unggah dokumen', 'Gunakan JPG, PNG, atau WebP maksimal 5 MB dan baca persetujuan penggunaan data sebelum mengajukan.'),
        ];
    } else if (page.includes('payment-methods')) {
        content = [
            step('#method-new', 'Tambahkan metode', context === 'admin' ? 'Metode platform digunakan sebagai tujuan pembayaran pemesanan baru. Lengkapi informasi tujuan dengan benar.' : 'Lengkapi tujuan pencairan hasil sewa. Perubahan metode tidak mengubah salinan data transaksi yang sudah tercatat.'),
            step('[data-payment-method-form] select', 'Pilih jenis metode', 'Pilihan jenis metode menentukan kolom rekening, e-wallet, atau gambar QRIS yang perlu diisi.'),
        ];
    } else {
        content = [
            step('[data-activity-list]', 'Aktivitas yang perlu ditangani', 'Mulai dari tugas yang membutuhkan tindakan. Tautan tugas membuka bagian yang relevan sesuai kewenangan Anda.'),
            ...lists,
            step('main table, main .owner-records', 'Daftar dan tindakan', 'Periksa informasi dan status tiap baris. Pilih Detail untuk meninjau pemesanan atau Edit untuk mengubah barang.'),
            step('main .catalog-card a, main .owned-item-card a', 'Detail barang', 'Buka barang untuk membaca informasi lengkap dan tindakan yang tersedia bagi akun Anda.'),
        ];
    }
    const account = context === 'owner' || context === 'admin' ? [
        step('[data-workspace-route="my-items.index"]', 'Barang Saya', 'Buka daftar barang Anda untuk memeriksa informasi, mengedit, dan mengelola publikasi serta persyaratan setiap barang.'),
        step('[data-workspace-route="admin.users.index"]', 'Pengguna', 'Cari akun dan peran pengguna dari daftar yang tersedia bagi administrator.'),
        step('[data-workspace-route="admin.categories.index"]', 'Kategori', 'Kelola pengelompokan barang dan status kategori dari area administrator.'),
        step('[data-workspace-route="admin.items.index"]', 'Barang marketplace', 'Periksa informasi barang dan pemilik melalui daftar barang administrator.'),
        step('[data-workspace-route="admin.bookings.index"]', 'Pemesanan administrator', 'Telusuri transaksi dan buka detail untuk memeriksa alur, bukti, serta tindakan yang diizinkan.'),
        link('create-item', 'Tambahkan barang', 'Gunakan Tambah Barang untuk memasukkan foto, informasi, harga, lokasi, dan persyaratan barang baru.'),
        link('incoming', 'Pemesanan masuk', 'Tinjau pengajuan penyewa, tawaran biaya pengiriman, serta serah terima yang menjadi tanggung jawab pemilik.'),
        link('admin-finance', 'Keuangan administrator', 'Periksa pembayaran, pengembalian dana, dan pencairan sesuai antrean serta kewenangan administrator.'),
        link('admin-identity', 'Verifikasi identitas', 'Buka antrean identitas untuk memeriksa pengajuan melalui akses berwenang.'),
        link('payouts', 'Pencairan pemilik', 'Pantau hasil sewa dan kelayakan pencairan setelah pengembalian serta pemeriksaan selesai.'),
    ] : context === 'renter' ? [
        link('my-bookings', 'Penyewaan Saya', 'Setelah mengajukan pemesanan, buka bagian ini untuk memantau persetujuan, pembayaran, pemeriksaan kondisi, dan pengembalian.'),
    ] : context === 'guest' && page === 'home' ? [link('login', 'Masuk atau daftar akun', 'Gunakan Masuk jika sudah memiliki akun. Tautan pendaftaran tersedia pada halaman masuk untuk pengguna baru.')] : [];
    return [heading, ...navigation, ...content, ...(marketplaceEnabled ? account : [])];
}
