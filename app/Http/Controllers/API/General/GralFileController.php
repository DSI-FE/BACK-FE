<?php

namespace App\Http\Controllers\API\General;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\General\GralFile;

class GralFileController extends Controller
{
    public function getImage($id)
    {

        $img = GralFile::findOrFail($id);
        $imgRoute   = storage_path('app/'.$img->route.'/'.$img->name);
        
        $exists = file_exists($imgRoute);


        return response()->file($imgRoute);
    }
}
