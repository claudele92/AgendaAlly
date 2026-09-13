<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\Gallery;
use App\Models\Settings;
use Illuminate\Http\UploadedFile;
use Str;
use Throwable;

class FileHelper
{
    public const imageExtensions = [
        'png',
        'jpg',
        'jpeg',
        'webp',
        'svg',
        'jfif',
        'avif',
        'gif',
    ];

    /**
     * Upload file function
     * @param UploadedFile $file
     * @param string $path
     * @return array
     */
    public static function uploadFile(UploadedFile $file, string $path): array
    {
        try {
            $isAws = Settings::where('key', 'aws')->first();

            $options = [];

            if (data_get($isAws, 'value')) {
                $options = ['disk' => 's3'];
            }

            $id   = auth('sanctum')->id() ?? '0001';
            $uuid = Str::uuid();
            $ext  = $file->getClientOriginalExtension();
            $dir  = $ext;

            if (in_array($file->getClientOriginalExtension(), self::imageExtensions)) {

                $dir  = 'images';

                $ext = strtolower(
                    preg_replace('#.+\.([a-z]+)$#i', '$1',
                        str_replace(self::imageExtensions, '.webp', $file->getClientOriginalName())
                    )
                );

            }

            $fileName = "$id-$uuid.$ext";

            $url = $file->storeAs("public/$dir/$path", $fileName, $options);

            // config('app.img_host') is a plain env('IMG_HOST') with no
            // default (see config/app.php) - if it's ever unset on a given
            // environment, string concatenation silently turns this into
            // "" . "storage/images/...", a bare relative path with no
            // host. That's not a URL a browser (or next/image, which
            // rejects it outright rather than trying to load it) can ever
            // resolve - it crashed the entire storefront the one time this
            // happened for real (a Settings logo upload). Falling back to
            // config('app.url') - which always has a real value, defaulting
            // to 'http://localhost' - guarantees this never degrades below
            // a syntactically valid absolute URL, even if it points at the
            // wrong host until IMG_HOST is actually configured.
            $imgHost = rtrim(config('app.img_host') ?: config('app.url'), '/') . '/';

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $imgHost . (!data_get($isAws, 'value') ? str_replace('public/', 'storage/', $url) : $url)
            ];
        } catch (Throwable $e) {

            $message = $e->getMessage();

            if ($message === "Class \"finfo\" not found") {
                $message = 'You need on php file info extension';
            }

            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_400,
                'message' => $message
            ];
        }
    }

    /**
     * Delete file function
     * @param $path
     * @return mixed
     */
    public static function deleteFile($path): mixed
    {
        return Gallery::where('path', $path)->delete();
    }

}
