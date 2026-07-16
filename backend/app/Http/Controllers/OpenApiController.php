<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class OpenApiController extends Controller
{
    public function show(): JsonResponse
    {
        if (class_exists(Generator::class)) {
            $spec = app(Generator::class)->generate();

            return response()->json($spec->toArray());
        }

        $path = config('scramble.export_path');
        if (is_string($path) && File::isFile($path)) {
            $json = json_decode(File::get($path), true);

            return response()->json(is_array($json) ? $json : []);
        }

        return response()->json([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Webino ERP API', 'version' => '1.0.0'],
            'paths' => new \stdClass,
        ]);
    }
}
