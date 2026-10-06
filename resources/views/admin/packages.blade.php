@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div><h1 class="fw-bold mb-1">Packages</h1><p class="text-muted mb-0">Create, update, feature, or remove your catering packages.</p></div>
        <a class="btn luxury-btn" href="{{ route('admin.packages.create') }}">Add package</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Package</th><th>Base rate</th><th class="d-none d-md-table-cell">Featured</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($packages as $package)
            <tr>
                <td><strong>{{ $package->name }}</strong><br><small class="text-muted">{{ str($package->description)->limit(50) }}</small></td>
                <td class="text-nowrap"><span class="fw-bold">PHP {{ number_format($package->price, 2) }}</span></td>
                <td class="d-none d-md-table-cell"><span class="badge-soft {{ $package->is_featured ? '' : 'badge-primary' }}">{{ $package->is_featured ? 'Featured' : 'Regular' }}</span></td>
                <td class="text-end"><div class="table-actions" role="group" aria-label="Actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.packages.edit', $package) }}">Edit</a><form class="d-inline" method="POST" action="{{ route('admin.packages.destroy', $package) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No packages yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
