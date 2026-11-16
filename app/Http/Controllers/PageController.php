<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function browse(): View
    {
        return view('pages.placeholder', [
            'title' => 'Jelajahi Barang',
            'heading' => 'Katalog barang segera tersedia.',
            'description' => 'Tahap 1 hanya menyiapkan fondasi. Fitur pencarian, filter, dan detail barang akan dibuat pada tahap berikutnya.',
        ]);
    }

    public function rentOut(): View
    {
        return view('pages.placeholder', [
            'title' => 'Sewakan Barang',
            'heading' => 'Formulir penyewaan barang segera tersedia.',
            'description' => 'Informasi pengelolaan barang tersedia melalui Dasbor Pemilik.',
        ]);
    }
}
