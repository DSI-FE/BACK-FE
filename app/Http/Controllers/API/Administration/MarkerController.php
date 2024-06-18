<?php

namespace App\Http\Controllers\API\Administration;

use App\Http\Controllers\Controller;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkerController extends Controller
{
    public function getFromFile(Request $request) {
        try {
            $some_variable = null;
            DB::transaction(function () use ($request, &$some_variable) {
                $user = 1;

                $storage_path = storage_path('app/markFiles/');

                if (!file_exists($storage_path)) {
                    mkdir($storage_path, 0777, true);
                }

                $file = $request->file('file');
                $data = [];

                if ($file) {
                    $path = $file->path();
                    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                    foreach ($lines as $idx => $line) {
                        $columns = explode("\t", $line);
                        $id = (int)trim($columns[0]);
                        $timestamp = date_format(date_create(trim($columns[1])), 'Y-m-d H:i:s');

                        $data[] = [
                            'adm_employee_id' => $id,
                            'datetime' => $timestamp,
                        ];
                    }
                }

                $chunkSize = 100;
                $chunks = array_chunk($data, $chunkSize);

                foreach ($chunks as $chunk) {
                    // InsertarRegistros::insert($chunk);
                }
            });
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
        }
    }
}
