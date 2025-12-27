<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductImage;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class ImageUploadController extends Controller
{
    protected ImageUploadService $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Upload product images for an order
     */
    public function uploadProductImages(Request $request, $orderId)
    {
        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ]);

        // Check order exists and user owns it
        $order = Order::findOrFail($orderId);
        
        if ($order->seller_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if adding these images would exceed the limit
        $currentImageCount = $order->images()->count();
        $newImageCount = count($request->file('images'));
        
        if ($currentImageCount + $newImageCount > 5) {
            return response()->json([
                'message' => 'Maximum 5 images allowed per order'
            ], 422);
        }

        $uploadedImages = [];
        $position = $currentImageCount; // Start position from current count

        foreach ($request->file('images') as $file) {
            try {
                $imageData = $this->imageService->uploadProductImage($file, $orderId, $position);
                
                $image = ProductImage::create([
                    'order_id' => $orderId,
                    'file_path' => $imageData['file_path'],
                    'file_name' => $imageData['file_name'],
                    'mime_type' => $imageData['mime_type'],
                    'file_size' => $imageData['file_size'],
                    'position' => $imageData['position'],
                    'is_primary' => $position === 0, // First image is primary
                ]);

                $uploadedImages[] = $image;
                $position++;
            } catch (\Exception $e) {
                // If any upload fails, return error
                return response()->json([
                    'message' => 'Image upload failed: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => collect($uploadedImages)->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'file_name' => $image->file_name,
                    'position' => $image->position,
                    'is_primary' => $image->is_primary,
                ];
            })
        ], 201);
    }

    /**
     * Delete a product image
     */
    public function deleteProductImage($imageId)
    {
        $image = ProductImage::findOrFail($imageId);
        
        // Check if user owns the order
        if ($image->order->seller_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete file from storage
        $this->imageService->deleteImage($image->file_path);
        
        // Delete database record
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully'
        ], 200);
    }
}
