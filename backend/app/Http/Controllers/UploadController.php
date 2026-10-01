<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Handle file uploads for form fields.
     * Returns JSON (used by the DynamicField component via fetch).
     */
    public function upload(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json(['ok' => false, 'message' => 'No file provided.'], 400);
        }

        $file        = $request->file('file');
        $extension   = $file->getClientOriginalExtension();
        $fileName    = Str::random(24) . '.' . $extension;
        $destination = public_path('uploads');

        if (! file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $file->move($destination, $fileName);

        return response()->json([
            'ok'            => true,
            'url'           => url('uploads/' . $fileName),
            'file_name'     => $fileName,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }
}
