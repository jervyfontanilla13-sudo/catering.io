<?php

namespace App\Http\Controllers;

use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminGalleryController extends Controller
{
    public function index()
    {
        return view('admin.gallery', ['galleryItems' => GalleryItem::latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['title'] = 'Gallery image';
        $data['image_path'] = $request->file('image')->store('gallery', 'public');
        GalleryItem::create($data);

        return back()->with('success', 'Gallery image added.');
    }

    public function update(Request $request, GalleryItem $gallery)
    {
        $data = $this->validated($request, false);
        if ($request->hasFile('image')) {
            $oldImagePath = $gallery->image_path;
            $newImagePath = $request->file('image')->store('gallery', 'public');
            $data['image_path'] = $newImagePath;
        }
        $gallery->update($data);

        if (isset($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return back()->with('success', 'Gallery item updated.');
    }

    public function destroy(GalleryItem $gallery)
    {
        Storage::disk('public')->delete($gallery->image_path);
        $gallery->delete();

        return back()->with('success', 'Gallery item deleted.');
    }

    private function validated(Request $request, bool $imageRequired = true): array
    {
        $data = $request->validate([
            'is_featured' => ['nullable', 'boolean'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['image']);
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
