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
                <h1 class="fw-bold mb-2">Choose a new password</h1>
                <p class="text-muted">{{ \App\Support\AdminPasswordRules::helperText() }}</p>

                @if(session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control form-control-lg @error('email') is-invalid @enderror" autocomplete="email" aria-invalid="@error('email') true @else false @enderror" required>
                        @error('email')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <div class="input-group">
                            <input id="password" type="password" name="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="new-password" minlength="{{ \App\Support\AdminPasswordRules::minimumLength() }}" aria-invalid="@error('password') true @else false @enderror" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggle-password-reset" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mb-3" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <div class="input-group">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control form-control-lg" autocomplete="new-password" minlength="{{ \App\Support\AdminPasswordRules::minimumLength() }}" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggle-password-confirm" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2" type="submit">Reset password</button>
                </form>
                <script>
                    function setupPasswordToggle(inputId, buttonId) {
                        document.getElementById(buttonId).addEventListener('click', function() {
                            const input = document.getElementById(inputId);
                            const icon = this.querySelector('i');
                            if (input.type === 'password') {
                                input.type = 'text';
                                icon.classList.remove('fa-eye');
                                icon.classList.add('fa-eye-slash');
                            } else {
                                input.type = 'password';
                                icon.classList.remove('fa-eye-slash');
                                icon.classList.add('fa-eye');
                            }
                        });
                    }
                    setupPasswordToggle('password', 'toggle-password-reset');
                    setupPasswordToggle('password_confirmation', 'toggle-password-confirm');
                </script>
            </div>
        </div>
    </div>
</div>
@endsection
