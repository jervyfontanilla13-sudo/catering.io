@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div><h1 class="fw-bold mb-1">Services</h1><p class="text-muted mb-0">Create, update, feature, or remove your service offerings.</p></div>
        <a class="btn luxury-btn" href="{{ route('admin.services.create') }}">Add service</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Service</th><th class="d-none d-md-table-cell">Icon</th><th>Status</th><th class="d-none d-md-table-cell">Featured</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($services as $service)
            <tr>
                <td><strong>{{ $service->name }}</strong><br><small class="text-muted">{{ str($service->description)->limit(70) }}</small></td>
                <td class="d-none d-md-table-cell">{{ $service->icon ?: '—' }}</td>
                <td><span class="badge-soft {{ $service->is_enabled ? '' : 'badge-primary' }}">{{ $service->is_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                <td class="d-none d-md-table-cell"><span class="badge-soft {{ $service->is_featured ? '' : 'badge-primary' }}">{{ $service->is_featured ? 'Featured' : 'Regular' }}</span></td>
                <td class="text-end"><div class="table-actions" role="group" aria-label="Actions"><form class="d-inline" method="POST" action="{{ route('admin.services.toggle', $service) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $service->is_enabled ? 'Disable' : 'Enable' }}</button></form><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.services.edit', $service) }}">Edit</a><form class="d-inline" method="POST" action="{{ route('admin.services.destroy', $service) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No services yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
