<?php

namespace App\Services;

use App\Models\ItemPhoto;
use finfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemPhotoStorage
{
    public const MaxBytes = 5 * 1024 * 1024;

    public const MimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    public function persist(ItemPhoto $photo, string $contents): bool
    {
        if ($contents === '' || strlen($contents) > self::MaxBytes) {
            return false;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! in_array($mimeType, self::MimeTypes, true)) {
            return false;
        }

        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            return false;
        }

        try {
            if (fwrite($stream, $contents) !== strlen($contents) || ! rewind($stream)) {
                return false;
            }

            return DB::transaction(function () use ($photo, $mimeType, $stream): bool {
                $current = ItemPhoto::query()->lockForUpdate()->find($photo->id);
                if (! $current || $current->file_token !== null) {
                    return false;
                }

                DB::table('item_photo_files')->insert([
                    'item_photo_id' => $current->id,
                    'mime_type' => $mimeType,
                    'contents' => $stream,
                ]);
                $current->update(['file_token' => (string) Str::uuid()]);

                return true;
            });
        } finally {
            fclose($stream);
        }
    }
}
