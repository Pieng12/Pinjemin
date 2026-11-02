@extends('layouts.app')
@section('title', 'Syarat & Ketentuan - Pinjemin')
@section('nav_title')
    Syarat & Ketentuan
@endsection
@section('meta_description', 'Aturan menyewa dan menyewakan barang di Pinjemin: pembayaran, DP, pemeriksaan kondisi, pengembalian, refund, dan pengaduan.')
@section('content')
@php
    $sections = [
        ['id' => 'akun', 'title' => 'Akun dan peran Pinjemin', 'points' => [
            'Pinjemin mempertemukan pemilik barang dengan penyewa dan mencatat booking, pembayaran manual, pemeriksaan kondisi, pengaduan, serta pencairan melalui admin.',
            'Gunakan informasi akun yang benar dan jaga kerahasiaan password. Laporkan kepada admin jika ada penggunaan akun atau transaksi yang tidak kamu kenali.',
        ]],
        ['id' => 'pemilik', 'title' => 'Aturan untuk pemilik barang', 'points' => [
            'Sewakan barang milik sendiri atau barang yang kamu berhak sewakan. Barang harus layak digunakan dan tidak termasuk barang yang dilarang untuk diperdagangkan atau disewakan.',
            'Jelaskan kondisi, cacat, fungsi, aksesori, harga, jumlah unit, lokasi, dan aturan penggunaan dengan jujur. Foto serta deskripsi harus mewakili barang yang diserahkan.',
            'Serahkan barang sesuai kesepakatan, jelaskan petunjuk penggunaan yang diperlukan, dan periksa pengembalian secara wajar berdasarkan bukti kedua pihak.',
        ]],
        ['id' => 'penyewa', 'title' => 'Aturan untuk penyewa', 'points' => [
            'Baca informasi dan persyaratan barang sebelum memesan. Gunakan barang sesuai tujuan, petunjuk, dan aturan pemilik yang telah disepakati.',
            'Jaga barang dan aksesori selama berada dalam penguasaanmu. Jangan menjual, menggadaikan, menyewakan kembali, memodifikasi, atau menyerahkan barang kepada pihak lain tanpa izin pemilik.',
            'Segera catat dan laporkan ketidaksesuaian atau masalah yang ditemukan. Kembalikan barang beserta aksesori yang diterima sesuai periode booking.',
        ]],
        ['id' => 'persyaratan', 'title' => 'Persyaratan dan persetujuan booking', 'points' => [
            'Pemilik menentukan kewajiban foto kondisi dan verifikasi identitas per barang. Penyewa menyetujui persyaratan tersebut saat mengajukan booking dan tidak dapat mematikannya untuk booking itu.',
            'Persyaratan, aturan barang, dan versi ketentuan disimpan saat pengajuan. Perubahan pengaturan barang hanya berlaku untuk booking berikutnya.',
            'Booking lama mengikuti rincian dan ketentuan yang tercatat pada booking tersebut. Aturan baru tidak diterapkan secara retroaktif.',
        ]],
        ['id' => 'periode', 'title' => 'Periode dan ketersediaan', 'points' => [
            'Tanggal mulai dan akhir dihitung inklusif: tanggal 18–20 berarti tiga hari sewa. Tanggal dan waktu transaksi ditampilkan menggunakan WIB.',
            'Pemesanan mengikuti sisa stok per hari. Tanggal yang stoknya tidak cukup tidak dapat dipilih; pengajuan menunggu persetujuan belum menahan stok.',
            'Stok ditahan pada tahap konfirmasi ongkir, pembayaran yang masih berlaku, pemeriksaan pembayaran, DP terverifikasi, dan booking disetujui sesuai statusnya. Pembatalan atau kedaluwarsa melepaskan reservasi terkait.',
        ]],
        ['id' => 'pembayaran', 'title' => 'Harga, DP, dan pembayaran', 'points' => [
            'Total booking baru adalah biaya sewa ditambah ongkir jika ada. DP merupakan bagian dari biaya sewa, bukan biaya tambahan atau uang jaminan. Pilihan DP mengikuti kebijakan dan nominal barang.',
            'Bayar ke metode Pinjemin yang tercantum pada booking dan unggah bukti transaksi asli. Admin memverifikasi dana secara manual; unggahan bukti belum berarti pembayaran telah diterima.',
            'Batas pembayaran maksimal 24 jam atau akhir tanggal mulai sewa dalam WIB, mana yang lebih cepat. Bukti yang masuk sebelum tenggat diperiksa terlebih dahulu. Penolakan disertai alasan dan pengiriman ulang mengikuti sisa tenggat.',
            'Barang hanya boleh diserahkan setelah lunas. Booking lama yang menggunakan pembayaran langsung kepada pemilik tetap mengikuti catatan dan metode pada booking tersebut.',
        ]],
        ['id' => 'komisi', 'title' => 'Komisi dan pencairan', 'points' => [
            'Komisi Pinjemin adalah 5% dari subtotal sewa, dipotong dari pencairan pemilik. Ongkir tidak dikenai komisi. Komisi ini adalah biaya layanan, bukan pajak.',
            'Contoh: sewa Rp100.000 dan ongkir Rp20.000 menghasilkan komisi Rp5.000 serta pencairan Rp115.000 kepada pemilik.',
            'Pencairan dapat diproses setelah pembayaran lunas, pengembalian fisik terkonfirmasi, serta pemeriksaan kedua pihak atau penyelesaian admin selesai. Tidak ada tambahan masa tunggu 24 jam, tetapi transfer tetap diproses manual.',
            'Keberatan kondisi atau masalah yang belum selesai menahan pencairan. Penahanan tidak boleh dilepas untuk melewati pemeriksaan yang belum selesai.',
        ]],
        ['id' => 'pengiriman', 'title' => 'Pengambilan dan pengiriman', 'points' => [
            'Pengambilan langsung tersedia. Pengiriman hanya tersedia jika diaktifkan pemilik. Ongkir berbayar, termasuk tawaran Rp0 pada barang yang tidak menjanjikan gratis ongkir, harus disetujui penyewa.',
            'Ongkir hanya mencakup pengiriman kepada penyewa, satu kali per booking. Penyewa bertanggung jawab mengembalikan barang ke lokasi pemilik. Tidak ada integrasi kurir atau pelacakan otomatis.',
            'Alamat lengkap pengambilan ditampilkan setelah booking final dan hanya untuk pihak terkait serta admin. Gunakan alamat dan penerima yang benar saat memilih pengiriman.',
            'Pemilik mencatat kondisi dan kemasan saat mengirim; penyewa mencatat saat menerima. Pengiriman balik memakai urutan sebaliknya. Kerusakan selama perjalanan diperiksa berdasarkan bukti, bukan otomatis dibebankan kepada salah satu pihak.',
        ]],
        ['id' => 'identitas', 'title' => 'Verifikasi identitas', 'points' => [
            'Jika diwajibkan, penyewa dapat mengajukan booking sambil melengkapi verifikasi, tetapi pemilik belum dapat menerima atau menawarkan ongkir sebelum identitas disetujui admin.',
            'Verifikasi memakai KTP atau SIM tanpa selfie, berlaku maksimal satu tahun atau sampai dokumen kedaluwarsa jika lebih cepat. Ini pemeriksaan manual, bukan validasi Dukcapil atau jaminan keaslian identitas.',
            'Pemilik barang hanya melihat status pemenuhan persyaratan. Dokumen identitas tidak diberikan kepada pemilik sebagai tanggapan atas tuduhan. Pencabutan verifikasi karena dugaan penyalahgunaan memblokir penyerahan yang belum terjadi sampai diperiksa admin.',
        ]],
        ['id' => 'kondisi', 'title' => 'Foto kondisi dan serah terima', 'points' => [
            'Kedua pihak memeriksa fungsi, aksesori, cacat atau perbedaan kondisi, dan nomor seri jika ada. Masing-masing mengirim bukti sendiri saat penyerahan dan pengembalian.',
            'Jika foto diwajibkan, setiap pihak mengirim 1–5 foto per tahap. Jika tidak, foto tetap boleh dikirim sukarela. Gunakan JPG, PNG, atau WebP maksimal 5 MB per foto; jangan sertakan KTP/SIM.',
            'Untuk ambil sendiri, kedua pihak menyatakan siap lalu sesi unggah dibuka 30 menit. Untuk pengiriman, waktu masing-masing pihak dicatat terpisah. Sesi berakhir tidak berarti kondisi disetujui otomatis.',
            'Draf boleh diperbaiki sebelum dikirim. Bukti terkirim dikunci; tambahan bukti disimpan terpisah. Bukti pihak lain terlihat setelah kedua pihak mengirim atau sesi berakhir. Waktu unggah bukan jaminan waktu pengambilan foto.',
            'Setelah meninjau, pilih Kondisi Sesuai atau Ada Perbedaan dengan alasan. Berita acara mencatat bukti dan keputusan; dokumen belum lengkap diberi penanda dan bukan klaim tanda tangan elektronik tersertifikasi.',
        ]],
        ['id' => 'pengembalian', 'title' => 'Pengembalian dan keterlambatan', 'points' => [
            'Kembalikan barang dan aksesori sesuai tanggal pengembalian. Segera hubungi pemilik dan admin jika terjadi keterlambatan atau kendala; perpanjangan tidak terjadi otomatis.',
            'Pernyataan penyewa atau pengiriman balik belum berarti barang diterima pemilik. Penerimaan fisik harus dikonfirmasi pemilik atau ditetapkan admin berdasarkan bukti.',
            'Konfirmasi penerimaan fisik membebaskan stok dan menghentikan penanda keterlambatan. Pemeriksaan kondisi dapat tetap berlangsung, dan booking selesai setelah persetujuan kedua pihak atau penyelesaian admin.',
            'Pinjemin menandai keterlambatan, tetapi tidak mengenakan denda atau biaya tambahan otomatis.',
        ]],
        ['id' => 'masalah-barang', 'title' => 'Kerusakan, kehilangan, dan ketidaksesuaian', 'points' => [
            'Segera ajukan pengaduan jika barang tidak sesuai, rusak, hilang, atau ada dugaan penyalahgunaan. Sertakan kode booking, kronologi, serta bukti yang tersedia dari penyerahan dan pengembalian.',
            'Keterangan kedua pihak, kondisi awal, aksesori, dan bukti lain diperiksa admin. Aplikasi tidak otomatis menentukan penyebab kerusakan, menyatakan pencurian, atau membebankan kesalahan kepada salah satu pihak.',
            'Ganti rugi atau perubahan nominal memerlukan penanganan tersendiri berdasarkan bukti dan penyelesaian yang tercatat. Tidak ada tarif ganti rugi, pemotongan, atau penagihan otomatis.',
            'Verifikasi identitas dan dokumentasi membantu pemeriksaan, tetapi bukan asuransi atau jaminan barang pasti kembali. Penyelesaian melalui admin tidak menghilangkan hak pengguna menempuh jalur penyelesaian yang tersedia.',
        ]],
        ['id' => 'pembatalan', 'title' => 'Pembatalan dan refund', 'points' => [
            'Pemilik dan penyewa dapat membatalkan sebelum penyerahan fisik atau pengiriman kepada kurir dicatat. Pemilik wajib memberikan alasan. Bukti pembayaran yang sedang diperiksa harus diputuskan terlebih dahulu.',
            'Pembatalan yang sudah memiliki pembayaran terverifikasi menghasilkan refund seluruh nominal tersebut tanpa biaya pembatalan. Admin mencatat bukti transfer refund; penyewa perlu memberikan tujuan refund yang benar.',
            'Setelah penyerahan, pembatalan biasa tidak tersedia. Masalah ditangani melalui pemeriksaan dan pengaduan. Jangan menganggap pengaduan setelah penyerahan otomatis menghasilkan refund.',
        ]],
        ['id' => 'dokumen', 'title' => 'Dokumen dan pengaduan', 'points' => [
            'Tagihan, konfirmasi pembayaran, berita acara, serta riwayat keputusan dapat diakses sesuai status dan izin booking. Pembayaran diverifikasi manual oleh admin atau, untuk booking lama tertentu, pemilik; bukan konfirmasi dari bank atau payment gateway.',
            'Admin dapat menyelesaikan kasus pihak yang tidak kooperatif dengan alasan dan bukti. Keputusan dicatat sebagai keputusan admin, bukan persetujuan atas nama pengguna.',
            'Gunakan fitur pengaduan pada detail booking atau hubungi pinjemin99@gmail.com. Jangan menyebarkan dokumen identitas, membuat bukti palsu, mengancam, atau menuduh pihak lain tanpa dasar.',
        ]],
    ];
@endphp
<x-legal-document title="Syarat & Ketentuan" intro="Kenali hak dan tanggung jawabmu saat menyewa atau menyewakan barang. Aturan ini menjelaskan alur yang berlaku, dari pemesanan sampai barang kembali dan dana dicairkan." :sections="$sections" :version="config('legal.terms_version')" />
@endsection
