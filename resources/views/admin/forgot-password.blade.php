@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .input-group .btn-outline-secondary {
        border-left: 0;
        padding: 0.76rem 0.85rem;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    .input-group .form-control:focus ~ .btn-outline-secondary {
        border-color: #20201d;
    }
    @media (max-width: 576px) {
        .col-md-7 {
            padding: 0 1rem;
        }
    }
</style>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="admin-card p-4 p-lg-5">
                <h1 class="fw-bold mb-2">Reset Admin Password</h1>
                <p class="text-muted">Enter your Admin email and we will send a password reset link.</p>

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <button class="btn btn-primary w-100 py-2 mt-3" type="submit">Email reset link</button>
                </form>

                <div class="text-center mt-3"><a href="{{ route('admin.login') }}">Back to login</a></div>
                <p class="small text-muted mt-4 mb-0">Password reset links are available for active Primary Admin and Team Admin accounts.</p>
            </div>
        </div>
    </div>
</div>
@endsection
