<?php

namespace App\Http\Controllers;

use App\Services\ItemPhotoStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ItemPhotoController extends Controller
{
    public function __invoke(string $token): Response
    {
        $file = DB::table('item_photo_files')
            ->join('item_photos', 'item_photos.id', '=', 'item_photo_files.item_photo_id')
            ->where('item_photos.file_token', $token)
            ->first(['item_photo_files.mime_type', 'item_photo_files.contents']);

        abort_unless($file && in_array($file->mime_type, ItemPhotoStorage::MimeTypes, true), 404);
        $contents = is_resource($file->contents) ? stream_get_contents($file->contents) : $file->contents;
        abort_unless(is_string($contents) && $contents !== '', 404);

        return response($contents, 200, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
