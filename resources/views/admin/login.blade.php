@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .login-form-label {
        display: block;
        margin-bottom: .5rem;
    }

    .login-field-control,
    .login-password-field {
        height: 3rem;
    }

    .login-password-control,
    .login-password-toggle {
        height: 100%;
    }

    .login-field-control {
        padding: 0 .9rem;
        border-color: #dee2e6;
        border-radius: .5rem;
        background-color: #fff;
    }

    .login-password-field {
        display: flex;
        width: 100%;
        height: 3rem;
        overflow: hidden;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        background-color: #fff;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }

    .login-password-field:focus-within {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
    }

    .login-password-control {
        min-width: 0;
        flex: 1 1 auto;
        padding: 0 .9rem;
        border: 0;
        border-radius: 0;
        background-color: transparent;
        box-shadow: none !important;
    }

    .login-password-control:focus {
        border: 0;
        background-color: transparent;
        outline: 0;
    }

    .login-password-toggle {
        display: inline-flex;
        width: 3.25rem;
        flex: 0 0 3.25rem;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 0;
        border: 0;
        border-left: 1px solid #dee2e6;
        border-radius: 0;
        background-color: #fff;
        color: #6c757d;
        font-size: 1rem;
        line-height: 1;
    }

    .login-password-toggle:hover,
    .login-password-toggle:focus {
        background-color: #f8f9fa;
        color: #495057;
    }

    .login-password-toggle:focus-visible {
        position: relative;
        z-index: 1;
        outline: 2px solid #86b7fe;
        outline-offset: -3px;
        box-shadow: none;
    }

    .login-password-toggle i {
        display: block;
        width: 1rem;
        text-align: center;
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
                <div class="text-center mb-4">
                    <span class="hero-badge">Secure access</span>
                    <h1 class="fw-bold mt-3 mb-2">Admin Login</h1>
                    <p class="text-muted">Access the management dashboard securely.</p>
                </div>
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if($setupAvailable)
                    <div class="alert alert-info">
                        <strong class="d-block mb-1">Primary Administrator Setup Required</strong>
                        <p class="mb-2">Primary Administrator setup is required. Create the first administrator account to continue.</p>
                    </div>
                    <a href="{{ route('admin.setup') }}" class="btn btn-outline-primary w-100 mb-3">Set Up Primary Administrator</a>
                @endif
                <form method="POST" action="{{ route('admin.login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label login-form-label" for="email-login">Email</label>
                        <input type="email" id="email-login" name="email" class="form-control login-field-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label login-form-label" for="password-login">Password</label>
                        <div class="login-password-field">
                            <input type="password" id="password-login" name="password" class="form-control login-password-control" required>
                            <button type="button" class="login-password-toggle" id="toggle-password-login" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-end mb-3"><a href="{{ route('password.request') }}" class="small">Forgot password?</a></div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
                </form>
                <script>
                    document.getElementById('toggle-password-login').addEventListener('click', function() {
                        const input = document.getElementById('password-login');
                        const icon = this.querySelector('i');
                        if (input.type === 'password') {
                            input.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                            this.setAttribute('aria-label', 'Hide password');
                            this.setAttribute('aria-pressed', 'true');
                        } else {
                            input.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                            this.setAttribute('aria-label', 'Show password');
                            this.setAttribute('aria-pressed', 'false');
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</div>
@endsection
