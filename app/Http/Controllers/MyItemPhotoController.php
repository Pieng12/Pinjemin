<?php

namespace App\Http\Controllers;

use App\ItemStatus;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MyItemPhotoController extends Controller
{
    public function destroy(Item $item, ItemPhoto $photo): RedirectResponse
    {
        Gate::authorize('manage', $item);
        abort_unless($photo->item_id === $item->id, 404);

        $path = $photo->path;

        DB::transaction(function () use ($item, $photo): void {
            $wasPrimary = $photo->is_primary;
            $photo->delete();

            $nextPhoto = $item->photos()->first();

            if ($wasPrimary && $nextPhoto) {
                $nextPhoto->update(['is_primary' => true]);
            }

            if (! $nextPhoto) {
                $item->update([
                    'is_active' => false,
                    'status' => ItemStatus::Inactive,
                ]);
            }
        });

        Storage::disk('public')->delete($path);

        return back()->with('status', 'Foto barang berhasil dihapus.');
    }

    public function primary(Item $item, ItemPhoto $photo): RedirectResponse
    {
        Gate::authorize('manage', $item);
        abort_unless($photo->item_id === $item->id, 404);

        DB::transaction(function () use ($item, $photo): void {
            $item->photos()->update(['is_primary' => false]);
            $photo->update(['is_primary' => true]);
        });

        return back()->with('status', 'Foto utama berhasil diperbarui.');
    }
}
