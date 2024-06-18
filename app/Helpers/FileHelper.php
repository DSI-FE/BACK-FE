<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;

class FileHelper
{
    public static function fileNameUnique($path, $fileName) {
        $count = 0;
        $originalFileName = $fileName;

        while (File::exists($path . '/' . $fileName)) {
            $count++;
            $fileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '(' . $count . ').' . pathinfo($originalFileName, PATHINFO_EXTENSION);
        }

        return $fileName;
    }

    public static function downloadFile($model, $id) {
        $fileInfo = $model::findOrFail($id);
        $filePath = storage_path('app/public/' . $fileInfo->file_location . '/' . $fileInfo->name);

        if (File::exists($filePath)) {
            return response()->file($filePath, [
                'Content-Disposition' => 'attachment: filename="' . $fileInfo->original_name . '"'
            ]);
        } else {
            abort(404);
        }
    }
}
