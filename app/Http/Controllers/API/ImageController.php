<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Response;

class ImageController extends Controller
{
    public function getGeneralImage($imgName)
    {
        $imgRoute   = storage_path('app/public/general/'.$imgName);
        
        $exists = file_exists($imgRoute);

        if(!$exists) abort(404);

        return response()->file($imgRoute);
    }

    public function getEmpleadoFoto($empleadoFotoName)
    {
        $imgRoute   = storage_path('app/public/administracion/empleados/fotografias/'.$empleadoFotoName);
        
        $exists = file_exists($imgRoute);

        if(!$exists) abort(404);

        return response()->file($imgRoute);
    }
}