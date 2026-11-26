<?php

namespace App\Console\Commands;

use App\Models\ItemPhoto;
use App\Services\ItemPhotoStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('item-photos:persist')]
#[Description('Salin file foto barang yang masih ada dari disk publik ke database')]
class PersistItemPhotos extends Command
{
    public function handle(ItemPhotoStorage $photoStorage): int
    {
        $disk = Storage::disk('public');
        $persisted = 0;
        $skipped = 0;
        $photos = ItemPhoto::query()->whereNull('file_token')->where('path', 'like', 'items/%')
            ->where('path', '!=', 'items/demo/pinjemin-placeholder.png')->lazyById();

        foreach ($photos as $photo) {
            $stream = $disk->readStream($photo->path);
            if (! is_resource($stream)) {
                $skipped++;

                continue;
            }

            try {
                $contents = stream_get_contents($stream, ItemPhotoStorage::MaxBytes + 1);
            } finally {
                fclose($stream);
            }

            if (is_string($contents) && $photoStorage->persist($photo, $contents)) {
                $persisted++;
            } else {
                $skipped++;
            }
        }

        $this->info("{$persisted} foto disalin ke database. {$skipped} foto dilewati.");

        return self::SUCCESS;
    }
}
