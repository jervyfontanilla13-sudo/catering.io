@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div><a class="back-link" href="{{ route('admin.services.index') }}">← Back to services</a><h1 class="fw-bold mb-1">{{ $service->exists ? 'Edit service' : 'Add service' }}</h1></div></div>
    <form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}">
        @csrf @if($service->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Service name</label><input class="form-control" name="name" value="{{ old('name', $service->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Icon name</label><input class="form-control" name="icon" value="{{ old('icon', $service->icon) }}" placeholder="Optional icon or short label"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5">{{ old('description', $service->description) }}</textarea></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="is_featured" id="featured" @checked(old('is_featured', $service->is_featured))><label class="form-check-label" for="featured">Featured service</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="is_enabled" id="enabled" @checked(old('is_enabled', $service->exists ? $service->is_enabled : true))><label class="form-check-label" for="enabled">Service enabled</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn luxury-btn" type="submit">{{ $service->exists ? 'Update service' : 'Create service' }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">Cancel</a></div>
        </div>
    </form>
</div>
@endsection
