<?php

namespace App\Helpers;

use Exception;
use Intervention\Image\Facades\Image;

class ImageProcessing
{

    public static function employeeImage($image, $imageName, $thSize = 90)
    {
        try {
            $storagePath = 'public/administracion/empleados/';
            $storagePathImage = $storagePath . 'fotografias/';
            $storagePathThumbnail = $storagePath . 'fotografias_sm/';

            $newImageName = $imageName . '.' . $image->getClientOriginalExtension();

            $image->storeAs($storagePathImage, $newImageName);

            $thumbnail = Image::make($image)->resize($thSize, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $storagePath = storage_path('app/' . $storagePathThumbnail);

            $thumbnail->save($storagePath . $newImageName);

            return [
                'photo_name' => $newImageName,
                'photo_route' => 'app/' . $storagePathImage . $newImageName,
                'photo_route_sm' => 'app/' . $storagePathThumbnail . $newImageName,
            ];
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getImage($location, $fileName, $fileNameOriginal)
    {
        $filePath = storage_path($location . '/' . $fileName);
        if (file_exists($filePath)) {
            $mime = mime_content_type($filePath);

            $fileContents = file_get_contents(($filePath));
            $base64 = base64_encode($fileContents);

            $dataUrl = 'data:' . $mime . ';base64,' . $base64;

            $headers = [
                'Content-Disposition' => "attachment; filename=\"$fileNameOriginal\"",
            ];

            return response($dataUrl)->withHeaders($headers);
        }
        return null;
    }
}
