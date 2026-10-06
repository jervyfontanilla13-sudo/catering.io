<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Rules\UniqueNormalizedName;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminServiceController extends Controller
{
    public function index()
    {
        return view('admin.services', ['services' => Service::latest()->get()]);
    }

    public function create()
    {
        return view('admin.service-form', ['service' => new Service()]);
    }

    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));

        return redirect()->route('admin.services.index')->with('success', "{$service->name} service created.");
    }

    public function edit(Service $service)
    {
        return view('admin.service-form', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('admin.services.index')->with('success', "{$service->name} service updated.");
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return back()->with('success', 'Service deleted.');
    }

    public function toggle(Service $service)
    {
        $service->update(['is_enabled' => ! $service->is_enabled]);

        return back()->with('success', "{$service->name} service " . ($service->is_enabled ? 'enabled.' : 'disabled.'));
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $name = $request->input('name');
        if (is_string($name)) {
            $request->merge(['name' => \Illuminate\Support\Str::squish($name)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', new UniqueNormalizedName($service ?? new Service(), 'service')],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $base = Str::slug($data['name']);
        $slug = $base;
        $number = 2;

        while (Service::where('slug', $slug)->when($service, fn ($query) => $query->whereKeyNot($service->id))->exists()) {
            $slug = $base . '-' . $number++;
        }

        $data['slug'] = $slug;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_enabled'] = $request->boolean('is_enabled');

        return $data;
    }
}
