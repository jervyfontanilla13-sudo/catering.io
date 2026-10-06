<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Rules\UniqueNormalizedName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPackageController extends Controller
{
    public function index() { return view('admin.packages', ['packages' => Package::orderBy('price')->get()]); }
    public function create() { return view('admin.package-form', ['package' => new Package()]); }
    public function store(Request $request) { $package = Package::create($this->validated($request)); return redirect()->route('admin.packages.index')->with('success', "{$package->name} package created."); }
    public function edit(Package $package) { return view('admin.package-form', compact('package')); }
    public function update(Request $request, Package $package) { $oldImage = $package->image_path; $data = $this->validated($request, $package); $package->update($data); if (isset($data['image_path']) && $oldImage) Storage::disk('public')->delete($oldImage); return redirect()->route('admin.packages.index')->with('success', "{$package->name} package updated."); }
    public function destroy(Package $package) { $image = $package->image_path; $package->delete(); if ($image) Storage::disk('public')->delete($image); return back()->with('success', 'Package deleted.'); }

    private function validated(Request $request, ?Package $package = null): array
    {
        $name = $request->input('name');
        if (is_string($name)) {
            $request->merge(['name' => \Illuminate\Support\Str::squish($name)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', new UniqueNormalizedName($package ?? new Package(), 'package')], 'description' => ['nullable', 'string'], 'price' => ['required', 'numeric', 'min:0'],
            'menu' => ['nullable', 'string'],
            'freebies' => ['nullable', 'string'], 'addons' => ['nullable', 'string'], 'event_type' => ['nullable', 'string', 'max:255'], 'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('packages', 'public');
        }
        unset($data['image']);
        $base = Str::slug($data['name']); $slug = $base; $number = 2;
        while (Package::where('slug', $slug)->when($package, fn ($query) => $query->whereKeyNot($package->id))->exists()) $slug = $base . '-' . $number++;
        $data['slug'] = $slug;
        $data['is_featured'] = $request->boolean('is_featured');
        return $data;
    }
}
