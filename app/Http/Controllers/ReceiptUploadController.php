<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptUploadController extends Controller
{
    /**
     * Upload and store receipt image.
     */
    public function upload(Request $request)
    {
        // 1. Multipart file upload
        if ($request->hasFile('receipt') || $request->hasFile('image')) {
            $file = $request->file('receipt') ?: $request->file('image');

            $request->validate([
                'receipt' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
            ]);

            $filename = 'receipt_' . date('Ymd_His') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('receipts', $filename, 'public');

            $url = Storage::url($path);

            return response()->json([
                'status' => 'success',
                'message' => 'Foto nota berhasil diunggah.',
                'url' => $url,
                'path' => $path
            ]);
        }

        // 2. Base64 upload
        if ($request->filled('image_base64')) {
            $base64Data = $request->input('image_base64');
            $extension = 'jpg';

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $extension = strtolower($matches[1]);
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            }

            $decoded = base64_decode($base64Data);
            if ($decoded === false) {
                return response()->json(['status' => 'error', 'message' => 'Data gambar base64 tidak valid.'], 400);
            }

            $filename = 'receipt_' . date('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
            $path = 'receipts/' . $filename;
            Storage::disk('public')->put($path, $decoded);

            $url = Storage::url($path);

            return response()->json([
                'status' => 'success',
                'message' => 'Foto nota berhasil disimpan.',
                'url' => $url,
                'path' => $path
            ]);
        }

        return response()->json(['status' => 'error', 'message' => 'Tidak ada file gambar yang diunggah.'], 400);
    }
}
