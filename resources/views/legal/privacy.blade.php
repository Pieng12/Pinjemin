@extends('layouts.app')
@section('title', 'Kebijakan Privasi - Pinjemin')
@section('nav_title', 'Kebijakan Privasi')
@section('meta_description', 'Pelajari penggunaan data akun, identitas KTP/SIM, bukti pembayaran, dan dokumentasi kondisi barang di Pinjemin.')
@section('content')
@php
    $sections = [
        ['id' => 'data-akun', 'title' => 'Data akun dan transaksi', 'points' => [
            'Pinjemin menggunakan nama, email, kata sandi yang di-hash, foto profil, serta telepon, kota, dan alamat yang Anda isi untuk menjalankan akun dan transaksi.',
            'Catatan pemesanan mencakup barang, periode, jumlah unit, metode penerimaan, penerima, biaya, keputusan, dan waktu tindakan. Persetujuan baru menyimpan versi aturan dan waktunya.',
            'Foto profil dan foto katalog barang ditampilkan pada bagian aplikasi yang terkait. Jangan memasukkan nomor identitas atau dokumen privat ke foto profil, foto barang, deskripsi, maupun catatan yang tidak ditujukan untuk verifikasi.',
        ]],
        ['id' => 'percakapan', 'title' => 'Percakapan dan laporan pesan', 'points' => [
            'Percakapan dimulai dari barang di katalog atau dari pemesanan terkait.',
            'Pinjemin menyimpan pesan, lampiran, waktu pengiriman, dan status baca untuk menyediakan riwayat percakapan. Riwayat dapat diakses oleh kedua peserta.',
            'Jika pesan dilaporkan, administrator dapat melihat pesan yang dipilih beserta lampirannya, keterangan laporan, dan informasi yang diperlukan untuk peninjauan. Gunakan formulir verifikasi atau pembayaran yang tersedia untuk menyampaikan dokumen dan bukti transaksi.',
        ]],
        ['id' => 'identitas', 'title' => 'Tujuan verifikasi identitas', 'points' => [
            'Jika memilih atau diwajibkan menjalani verifikasi, Anda memberikan jenis dokumen KTP/SIM, nama sesuai dokumen, nomor identitas, foto dokumen, dan tanggal kedaluwarsa jika berlaku. Tidak ada permintaan selfie.',
            'Data dipakai untuk pemeriksaan persyaratan identitas dan penanganan pengaduan berdasarkan dasar yang sah. Sebelum mengunggah, Anda menyetujui tujuan, akses, dan masa penyimpanan yang dijelaskan pada formulir.',
            'Pemeriksaan administrator berlaku maksimal satu tahun atau sampai dokumen kedaluwarsa jika lebih cepat. Pemeriksaan ini bukan validasi Dukcapil atau jaminan keaslian identitas.',
        ]],
        ['id' => 'akses', 'title' => 'Siapa yang dapat melihat data', 'points' => [
            'Dokumen identitas dan nomor lengkap hanya dapat diakses pemilik akun tersebut dan administrator berwenang. Pemilik barang melihat status pemenuhan persyaratan; akses administrator terhadap dokumen dicatat.',
            'Alamat lengkap pemilik ditampilkan setelah pemesanan final kepada pihak pemesanan serta administrator. Alamat penerima pengiriman digunakan oleh pemilik dan administrator untuk proses pemesanan terkait.',
            'Bukti pembayaran, QRIS, tujuan pembayaran, pengembalian dana, dan pencairan menggunakan akses sesuai peran serta pemesanan terkait. Penyewa lain atau pemilik barang lain tidak mendapat akses ke dokumen privat pemesanan Anda.',
            'Bukti kondisi dari pihak lain dibuka setelah kedua pihak mengirim atau sesi berakhir. Administrator dapat meninjaunya untuk pemeriksaan. Jangan mengunggah dokumen identitas sebagai foto kondisi.',
        ]],
        ['id' => 'penyimpanan', 'title' => 'Perlindungan dan masa penyimpanan', 'points' => [
            'Gambar identitas serta nomor lengkap disimpan terenkripsi pada penyimpanan privat. Akses gambar identitas menggunakan pemeriksaan izin dan respons yang meminta browser tidak menyimpannya dalam cache.',
            'Dokumen identitas disimpan maksimal selama masa verifikasi. Setelahnya, dokumen dihapus jika tidak terkait pemesanan aktif atau sengketa yang masih memerlukan bukti.',
            'Penundaan penghapusan karena kasus dicatat dan ditinjau setiap 30 hari. Pengajuan ditolak atau ditarik dihapus setelah 30 hari jika tidak terkait kasus. Riwayat keputusan minimal dapat dipertahankan tanpa salinan dokumen.',
            'Catatan transaksi, salinan data, berita acara, dan bukti terkait digunakan untuk menjalankan serta memeriksa transaksi. Pinjemin belum menetapkan satu tenggat penghapusan otomatis yang berlaku untuk seluruh catatan transaksi.',
        ]],
        ['id' => 'snapshot', 'title' => 'Catatan transaksi dan riwayat bukti', 'points' => [
            'Informasi pihak, barang, lokasi, metode pembayaran, dan ketentuan disalin ke pemesanan atau dokumen pada tahap yang terkait. Mengedit profil atau barang tidak mengubah salinan data transaksi lama.',
            'Bukti kondisi yang sudah dikirim dikunci. Perubahan berikutnya dicatat sebagai tambahan terpisah dengan pengunggah dan waktunya agar riwayat pemeriksaan tetap tersedia.',
            'Berita acara tidak memuat foto KTP/SIM atau nomor identitas lengkap. Waktu pengiriman foto bukan jaminan waktu pengambilan foto, dan aplikasi tidak memakai EXIF sebagai bukti waktu yang pasti.',
        ]],
        ['id' => 'sesi', 'title' => 'Cookie dan sesi masuk', 'points' => [
            'Aplikasi memakai cookie sesi untuk mempertahankan status masuk, melindungi formulir, serta menampilkan pesan dan isian setelah validasi. Pilihan Ingat saya digunakan jika Anda mengaktifkannya saat masuk.',
            'Keluar dari akun setelah menggunakan perangkat bersama. Jangan memberikan kata sandi, akses akun, atau bukti transaksi kepada pihak yang tidak berwenang.',
        ]],
        ['id' => 'permintaan-data', 'title' => 'Perubahan data dan permintaan bantuan', 'points' => [
            'Anda dapat memperbarui data yang tersedia di halaman profil dan mengelola pengajuan identitas melalui halaman Verifikasi Identitas. Perubahan profil tidak menghapus riwayat transaksi yang telah tercatat.',
            'Untuk pertanyaan, perbaikan, penarikan persetujuan identitas, atau permintaan penghapusan data lainnya, hubungi administrator melalui pinjemin99@gmail.com. Administrator dapat meminta verifikasi kepemilikan akun sebelum menindaklanjuti.',
            'Penarikan persetujuan atau penghapusan dapat memengaruhi pemenuhan persyaratan pemesanan. Jika ada pemesanan atau kasus aktif, kebutuhan penyimpanan bukti diperiksa terlebih dahulu dan penundaan dicatat.',
        ]],
        ['id' => 'pengungkapan', 'title' => 'Pengaduan dan pengungkapan dokumen', 'points' => [
            'Tuduhan kehilangan atau kerusakan tidak memberikan hak kepada pemilik barang untuk memperoleh salinan identitas penyewa. Pengungkapan kepada pihak berwenang memerlukan dasar yang sah dan catatan tindakan.',
            'Jangan mengirim atau menyebarkan dokumen pengguna lain melalui media sosial, deskripsi barang, atau percakapan publik. Sampaikan bukti melalui jalur privat yang tersedia untuk administrator.',
        ]],
    ];
@endphp
<x-legal-document title="Kebijakan Privasi" intro="Pahami data yang digunakan Pinjemin, tujuan penggunaannya, siapa yang dapat mengaksesnya, dan cara meminta bantuan terkait data akun Anda." :sections="$sections" :version="config('legal.privacy_version')" />
@endsection
