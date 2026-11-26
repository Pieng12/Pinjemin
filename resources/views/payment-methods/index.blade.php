@extends($platform ? 'layouts.admin' : 'layouts.owner')

@section('title', 'Metode Pembayaran - Pinjemin')

@section('content')
    <section class="page-shell max-w-6xl">
        @if ($platform)
            <div class="page-header">
                <div>
                    <p class="eyebrow">Pembayaran manual</p>
                    <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Rekening Penerimaan Pinjemin</h1>
                    <p class="mt-2 max-w-2xl text-zinc-600">Penyewa membayar ke tujuan ini. Semua administrator mengelola daftar yang sama; perubahan tidak mengubah instruksi pemesanan lama.</p>
                </div>
                <div class="grid gap-3"><p class="text-sm font-semibold text-zinc-600">{{ $activeMethodsCount }} / 10 metode aktif</p><x-button href="#tambah-metode">Tambah Metode</x-button></div>
            </div>
        @else
            <x-breadcrumb :items="['Dasbor Pemilik' => route('owner.dashboard'), 'Rekening Pencairan' => null]" />
            <x-owner-heading title="Rekening Pencairan" description="Tambahkan bank atau dompet digital untuk menerima dana dari Pinjemin." class="mt-4">
                @if ($methods->isNotEmpty())<x-button href="#tambah-metode"><i data-lucide="plus" aria-hidden="true" class="size-4"></i> Tambah Metode</x-button>@endif
            </x-owner-heading>
            <p class="mt-5 text-sm font-medium text-zinc-600">{{ $activeMethodsCount }} dari 10 metode aktif</p>
        @endif

        @if ($methods->isNotEmpty())
        <div class="mt-4 grid items-start gap-4 xl:grid-cols-2">
            @foreach($methods as $method)
                <article class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold wrap-anywhere text-zinc-950">{{ $method->displayName() }}</h2>
                                @if($method->is_primary)<span class="badge bg-blue-50 text-blue-700">Utama</span>@endif
                                <span class="badge {{ $method->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $method->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-600">{{ $method->account_name }}</p>
                            @if($method->account_identifier)<p class="mt-1 break-all font-mono text-sm text-zinc-900">{{ $method->account_identifier }}</p>@endif
                        </div>
                        @if($method->qris_path)
                            <img src="{{ route('payment-methods.image', $method) }}" alt="QRIS {{ $method->account_name }}" class="h-24 w-24 shrink-0 rounded-md border border-zinc-200 bg-white object-contain p-1">
                        @endif
                    </div>
                    @if($method->instructions)<p class="mt-4 whitespace-pre-line text-sm leading-6 text-zinc-600">{{ $method->instructions }}</p>@endif
                    <details class="group mt-3 border-t border-zinc-200 pt-2" @if(old('_payment_method_form') === 'method-'.$method->id) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 text-sm font-semibold text-brand-700">Edit metode <i data-lucide="chevron-down" class="size-4 group-open:rotate-180" aria-hidden="true"></i></summary>
                        <form id="method-{{ $method->id }}" data-form-id="method-{{ $method->id }}" data-error-bag="method-{{ $method->id }}" data-has-qris="{{ $method->qris_path ? 'true' : 'false' }}" method="POST" action="{{ route(($platform ? 'admin.' : '').'payment-methods.update', $method) }}" enctype="multipart/form-data" class="mt-4 grid gap-4" data-payment-method-form data-private-image-upload>
                            @csrf @method('PUT')
                            @include('payment-methods.partials.fields', ['current' => $method])
                            <div class="flex justify-end"><x-button type="submit">Simpan Perubahan</x-button></div>
                        </form>
                        <form method="POST" action="{{ route(($platform ? 'admin.' : '').'payment-methods.destroy', $method) }}" class="mt-3" data-confirm data-confirm-title="Hapus metode pembayaran?" data-confirm-message="Metode hilang dari profil, tetapi salinan data pemesanan lama tetap tersimpan." data-confirm-button="Hapus Metode">
                            @csrf @method('DELETE')
                            <x-button type="submit" variant="danger">Hapus</x-button>
                        </form>
                    </details>
                </article>
            @endforeach
        </div>
        @endif

        <div id="tambah-metode" class="card card-pad mt-6 max-w-3xl scroll-mt-24" tabindex="-1">
            <h2 class="text-lg font-semibold text-zinc-950">Tambah Metode</h2>
            <form id="method-new" data-form-id="method-new" data-error-bag="method-new" data-has-qris="false" method="POST" action="{{ route(($platform ? 'admin.' : '').'payment-methods.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4" data-payment-method-form data-private-image-upload>
                @csrf
                @include('payment-methods.partials.fields', ['current' => null])
                <div class="owner-form-actions mt-0"><x-button type="submit">Tambah Metode</x-button></div>
            </form>
        </div>
    </section>
@endsection
