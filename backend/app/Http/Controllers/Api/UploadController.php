<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json(['ok' => false, 'message' => 'No file provided.'], 400);
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::random(24) . '.' . $extension;

        // Store directly in public/uploads for immediate web access
        $destinationPath = public_path('uploads');
        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $file->move($destinationPath, $fileName);
        $url = url('uploads/' . $fileName);

        return response()->json([
            'ok'        => true,
            'url'       => $url,
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }
}
