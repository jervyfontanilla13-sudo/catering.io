@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Backups</h1>
            <p class="text-muted mb-0">Backups are encrypted before storage. Restoring replaces supported business data while preserving administrator accounts, sessions, and existing audit history.</p>
        </div>
        <div class="backup-page-controls">
            @include('admin.partials.backup-format-indicator', ['currentBackupFormat' => $currentBackupFormat])
            <div class="page-actions">
                <form method="POST" action="{{ route('admin.backups.create') }}">@csrf<button class="btn btn-primary" type="submit">Create Backup</button></form>
                <button class="btn btn-outline-primary" type="button" id="upload-backup-trigger">Upload Backup</button>
            </div>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @php($shownDatabaseStatus = session('database_status', $databaseStatus))
    <section class="backup-overview" aria-label="Backup and database status">
        <div class="backup-summary">
            <div>
                <span class="backup-label">Database status</span>
                <strong class="backup-status backup-status-{{ $shownDatabaseStatus['status'] }}">
                    <span aria-hidden="true">●</span> {{ $shownDatabaseStatus['label'] }}
                </strong>
                <small>{{ $shownDatabaseStatus['message'] }}</small>
            </div>
            <div>
                <span class="backup-label">Current backup format</span>
                <strong>{{ $currentBackupFormat['name'] }}</strong>
                <small>Encryption: Laravel protected</small>
            </div>
            <div>
                <span class="backup-label">Last successful backup</span>
                <strong>{{ ($lastSuccessfulBackup['created_at'] ?? null)?->format('F j, Y \a\t g:i A') ?? 'Not available' }}</strong>
                <small>{{ count($backups) }} available · {{ $compatibleBackupCount }} validated compatible</small>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.backups.check-database') }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary" type="submit">Check Database</button>
        </form>
    </section>
    <section class="database-recovery mb-4" aria-labelledby="database-recovery-title">
        <h2 id="database-recovery-title" class="h5 fw-bold mb-2">Database Recovery</h2>
        <p class="mb-0">Administrator account recovery and full database recovery require authorized server access. Follow the documented recovery procedure to restore access or recover the database from a validated backup.</p>
    </section>
    <div class="card">
        <div class="backup-list">
            @forelse($backups as $backup)
                <div class="backup-row">
                    <div>
                        <strong>{{ $backup['name'] }}</strong>
                        @if($backup['latest'])
                            <span class="badge text-bg-primary">Current</span>
                        @endif
                        <small class="d-block text-muted">{{ number_format($backup['size'] / 1024, 1) }} KB</small>
                        <div class="backup-metadata">
                            <span>{{ $backup['format'] }}</span>
                            @if($backup['older'])
                                <span aria-hidden="true">·</span>
                                <span>Older backup</span>
                            @endif
                            @if($backup['legacy'] && $backup['format'] !== 'Legacy format')
                                <span aria-hidden="true">·</span>
                                <span>Legacy format</span>
                            @endif
                        </div>
                        <small class="d-block {{ $backup['compatible'] === false ? 'text-danger' : 'text-muted' }}">
                            {{ $backup['compatible'] === true ? 'Compatible' : ($backup['compatible'] === false ? 'Not compatible with current system' : 'Compatibility unknown — validate first') }}
                        </small>
                        <small class="d-block text-muted">
                            Created: {{ $backup['created_at']?->format('F j, Y \a\t g:i A') ?? ($backup['legacy'] ? 'Legacy backup — date unavailable' : 'Date unavailable') }}
                        </small>
                        <span class="badge {{ $backup['encrypted'] ? 'text-bg-success' : 'text-bg-warning' }}">
                            {{ $backup['encrypted'] ? 'Protected' : 'Legacy · Unencrypted' }}
                        </span>
                        <span class="badge {{ $backup['validation_status'] === 'validated' ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $backup['validation_status'] === 'validated' ? 'Validated' : 'Not validated' }}
                        </span>
                    </div>
                    <div class="backup-actions">
                        <form method="POST" action="{{ route('admin.backups.download') }}">@csrf<input type="hidden" name="backup" value="{{ $backup['name'] }}"><button class="btn btn-sm btn-outline-secondary" type="submit">Download</button></form>
                        <form method="POST" action="{{ route('admin.backups.validate') }}">@csrf<input type="hidden" name="backup" value="{{ $backup['name'] }}"><button class="btn btn-sm btn-outline-primary" type="submit">Validate</button></form>
                        <form method="POST" action="{{ route('admin.backups.restore') }}" data-requires-password data-password-message="Restore {{ $backup['name'] }}? Created: {{ $backup['created_at']?->format('F j, Y \a\t g:i A') ?? 'Date unavailable' }}. Format: {{ $backup['format'] }}. Encryption: {{ $backup['encrypted'] ? 'Protected' : 'Legacy unencrypted' }}. Compatibility: {{ $backup['compatible'] === true ? 'Compatible' : 'Not verified' }}. This will replace supported business data; administrator accounts and audit history are preserved. An encrypted safety backup will be created first." @if(!$backup['encrypted']) data-legacy-backup @endif>@csrf<input type="hidden" name="backup" value="{{ $backup['name'] }}"><button class="btn btn-sm btn-outline-danger" type="submit" @disabled($backup['compatible'] !== true || $backup['validation_status'] !== 'validated') title="{{ $backup['compatible'] === true ? 'Restore business data from this backup' : 'Validate this backup before restoring.' }}">Restore</button></form>
                        <form method="POST" action="{{ route('admin.backups.delete') }}" data-requires-password data-password-message="Permanently delete this backup? This cannot be undone.">@csrf @method('DELETE')<input type="hidden" name="backup" value="{{ $backup['name'] }}"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
                    </div>
                </div>
            @empty
                <p class="mb-0 text-muted">No backups found.</p>
            @endforelse
        </div>
    </div>
</div>
<dialog id="upload-backup-dialog" aria-labelledby="upload-backup-title">
    <form id="upload-backup-form" method="POST" action="{{ route('admin.backups.upload') }}" enctype="multipart/form-data">
        @csrf
        <h2 id="upload-backup-title">Upload Backup</h2>
        <p class="text-muted mb-3">Encrypted backups are verified before they are stored. Legacy JSON backups are accepted and encrypted on upload.</p>
        <div class="mb-3">
            <label for="backup-file-input" class="form-label">Choose file</label>
            <input id="backup-file-input" name="backup_file" class="form-control" type="file" accept=".json,.enc,application/json,application/octet-stream" required>
        </div>
        <div class="small text-muted mb-3">Selected file: <span id="selected-backup-file-name">No file selected.</span></div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="upload-backup-cancel">Cancel</button>
            <button type="submit" class="btn btn-primary" id="upload-backup-submit">Upload Backup</button>
        </div>
    </form>
</dialog>
<dialog id="backup-password-dialog" aria-labelledby="backup-password-title">
    <form id="backup-password-dialog-form">
        <h2 id="backup-password-title">Confirm your password</h2>
        <p id="backup-password-message" class="text-muted"></p>
        <label for="backup-password-input" class="form-label">Administrator password</label>
        <input id="backup-password-input" type="password" class="form-control" autocomplete="current-password" required>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="backup-password-cancel">Cancel</button>
            <button type="submit" class="btn btn-danger">Continue</button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const uploadDialog = document.getElementById('upload-backup-dialog');
    const uploadTrigger = document.getElementById('upload-backup-trigger');
    const uploadForm = document.getElementById('upload-backup-form');
    const uploadSubmitButton = document.getElementById('upload-backup-submit');
    const fileInput = document.getElementById('backup-file-input');
    const selectedFileName = document.getElementById('selected-backup-file-name');

    const updateSelectedFile = () => {
        const file = fileInput.files && fileInput.files[0];
        selectedFileName.textContent = file ? file.name : 'No file selected.';
    };

    uploadTrigger.addEventListener('click', () => {
        fileInput.value = '';
        updateSelectedFile();
        uploadDialog.showModal();
    });

    document.getElementById('upload-backup-cancel').addEventListener('click', () => uploadDialog.close());
    fileInput.addEventListener('change', updateSelectedFile);

    uploadForm.addEventListener('submit', () => {
        uploadSubmitButton.disabled = true;
        uploadSubmitButton.textContent = 'Uploading...';
    });

    const dialog = document.getElementById('backup-password-dialog');
    const dialogForm = document.getElementById('backup-password-dialog-form');
    const passwordInput = document.getElementById('backup-password-input');
    let protectedForm = null;

    document.querySelectorAll('form[data-requires-password]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.passwordConfirmed === 'true') {
                delete form.dataset.passwordConfirmed;
                return;
            }
            event.preventDefault();
            if (form.hasAttribute('data-legacy-backup')) {
                const confirmed = window.confirm('This legacy backup is unencrypted. Restoring it will replace current system data. Continue only if you trust this file.');
                if (!confirmed) return;
                const confirmation = document.createElement('input');
                confirmation.type = 'hidden';
                confirmation.name = 'confirm_legacy';
                confirmation.value = '1';
                form.append(confirmation);
            }
            protectedForm = form;
            document.getElementById('backup-password-message').textContent = form.dataset.passwordMessage;
            passwordInput.value = '';
            dialog.showModal();
            passwordInput.focus();
        });
    });

    document.getElementById('backup-password-cancel').addEventListener('click', () => dialog.close());
    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!dialogForm.reportValidity() || !protectedForm) return;

        const confirmation = document.createElement('input');
        confirmation.type = 'hidden';
        confirmation.name = 'current_admin_password';
        confirmation.value = passwordInput.value;
        protectedForm.append(confirmation);
        protectedForm.dataset.passwordConfirmed = 'true';
        const form = protectedForm;
        protectedForm = null;
        dialog.close();
        form.requestSubmit();
    });
})();
</script>
<style>.backup-list{display:grid;gap:.75rem}.backup-overview{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;padding:1rem;border:1px solid var(--line);border-radius:var(--radius-sm, .5rem);background:var(--surface)}.backup-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;min-width:0;flex:1}.backup-summary>div{display:grid;gap:.2rem;min-width:0}.backup-summary strong{font-size:.95rem}.backup-summary small{color:var(--muted)}.backup-label{color:var(--muted);font-size:.75rem}.backup-status-healthy{color:#198754}.backup-status-unavailable,.backup-status-connection_error,.backup-status-recovery_required{color:#b42318}.database-recovery{padding:1rem;border:1px solid var(--line);border-radius:var(--radius-sm, .5rem)}.database-recovery p{color:var(--muted)}.backup-page-controls{display:flex;flex:0 1 auto;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.75rem 1rem;min-width:0}.backup-format-indicator{display:flex;flex-wrap:wrap;align-items:baseline;gap:.25rem .4rem;min-width:0;padding:.35rem .65rem;border:1px solid var(--line);border-radius:var(--radius-sm, .5rem);background:var(--surface);color:var(--ink);font-size:.8rem;line-height:1.35}.backup-format-indicator strong{font-size:inherit}.backup-format-current{color:var(--muted);font-size:.75rem}.backup-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 0;border-bottom:1px solid var(--line)}.backup-row:last-child{border-bottom:0}.backup-row strong{font-size:.85rem}.backup-metadata{display:flex;flex-wrap:wrap;align-items:center;gap:.2rem .35rem;margin-top:.2rem;color:var(--ink);font-size:.8rem}.backup-actions{display:flex;gap:.5rem;flex-wrap:wrap}.backup-actions form{margin:0}@media(max-width:767px){.backup-overview{flex-direction:column}.backup-summary{grid-template-columns:1fr 1fr}}@media(max-width:575px){.backup-summary{grid-template-columns:1fr}.backup-page-controls{flex:1 1 100%;justify-content:flex-start}.backup-page-controls .page-actions{width:100%}.backup-row{align-items:flex-start;flex-direction:column}.backup-actions{width:100%}.backup-actions form,.backup-actions .btn{flex:1;width:100%}}</style>
@endsection
