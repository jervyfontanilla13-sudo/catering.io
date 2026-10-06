@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <h1 class="fw-bold mb-1">Gallery</h1>
        <p class="text-muted mb-0">Add event photos and update the public gallery.</p>
    </div></div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="gallery-upload mb-4">
        <h2 class="h5 mb-3">Add photo</h2>
        <form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="new-gallery-image">Image file</label>
                <input id="new-gallery-image" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-gallery-crop-input>
                <small class="form-text">Upload a JPG, PNG, or WebP image up to 5 MB.</small>
                <div class="gallery-crop-result mt-2" data-crop-result hidden>
                    <img alt="Preview of the selected gallery crop">
                    <span>Crop ready. It will be saved when you upload the photo.</span>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="newFeatured">
                <label class="form-check-label" for="newFeatured">Featured image</label>
            </div>
            <button class="btn luxury-btn" type="submit">Upload photo</button>
        </form>
    </section>

    <div class="row g-3 gallery-grid">
        @forelse($galleryItems as $item)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <article class="gallery-admin-item">
                    <div class="gallery-admin-preview">
                        @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($item->image_path))
                            <img src="{{ route('gallery.image', ['path' => $item->image_path]) }}" alt="Catering event photo" loading="lazy">
                        @else
                            <div class="gallery-admin-missing" role="img" aria-label="Catering event image file unavailable"><span>Image file unavailable</span></div>
                        @endif
                        @if($item->is_featured)
                            <span class="gallery-admin-featured">★ Featured</span>
                        @endif
                    </div>
                    <div class="gallery-admin-controls">
                        <div class="gallery-admin-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm gallery-edit-toggle" aria-expanded="false" aria-controls="gallery-editor-{{ $item->id }}">Edit</button>
                            <form method="POST" action="{{ route('admin.gallery.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                        <div class="gallery-admin-editor-body" id="gallery-editor-{{ $item->id }}" hidden>
                            <form method="POST" action="{{ route('admin.gallery.update', $item) }}" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <label class="form-label" for="gallery-image-{{ $item->id }}">Replace image</label>
                                <input id="gallery-image-{{ $item->id }}" class="form-control mb-2" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-gallery-crop-input>
                                <div class="gallery-crop-result mb-2" data-crop-result hidden>
                                    <img alt="Preview of the replacement gallery crop">
                                    <span>Crop ready. It will replace the current image when you save.</span>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured{{ $item->id }}" @checked($item->is_featured)>
                                    <label class="form-check-label" for="featured{{ $item->id }}">Featured image</label>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Save changes</button>
                                    <button class="btn btn-outline-secondary btn-sm gallery-edit-cancel" type="button">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-4">No gallery images yet.</div>
        @endforelse
    </div>
</div>

<style>
    .gallery-upload { max-width: 540px; padding: 1.1rem 1.25rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .gallery-admin-item { overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); height: 100%; display: flex; flex-direction: column; }
    .gallery-admin-preview { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: #eaf0f2; }
    .gallery-admin-preview img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .gallery-admin-missing { display: grid; width: 100%; height: 100%; place-items: center; background: #eaf0f2; color: #71808b; font-size: .8rem; font-weight: 700; }
    body.dark-mode .gallery-admin-missing { background: #22343f; color: #b4c2c9; }
    .gallery-admin-featured { position: absolute; top: .65rem; left: .65rem; padding: .25rem .55rem; border-radius: 999px; background: var(--gold); color: #2c2014; font-size: .68rem; font-weight: 800; box-shadow: 0 2px 6px rgba(21,37,55,.18); }
    .gallery-admin-controls { padding: .85rem .9rem 1rem; }
    .gallery-admin-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .gallery-admin-actions form { margin: 0; }
    .gallery-admin-editor-body { margin-top: .85rem; padding-top: .85rem; border-top: 1px solid var(--line); }
    .gallery-crop-result { display: flex; align-items: center; gap: .75rem; color: var(--muted); font-size: .8rem; }
    .gallery-crop-result[hidden] { display: none; }
    .gallery-crop-result img { width: 96px; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .4rem; border: 1px solid var(--line); }
    .gallery-crop-modal { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 1rem; background: rgba(16, 20, 24, .76); }
    .gallery-crop-modal[hidden] { display: none; }
    .gallery-crop-dialog { width: min(100%, 760px); max-height: min(94vh, 900px); overflow: auto; padding: clamp(1rem, 3vw, 1.5rem); border-radius: 1rem; background: var(--surface); color: var(--text, #252525); box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .35); }
    .gallery-crop-stage { width: min(100%, 600px); margin: 0 auto; aspect-ratio: 4 / 3; position: relative; overflow: hidden; background: #171717; touch-action: none; user-select: none; cursor: grab; }
    .gallery-crop-stage:active { cursor: grabbing; }
    .gallery-crop-stage img { position: absolute; max-width: none; max-height: none; pointer-events: none; }
    .gallery-crop-controls { width: min(100%, 600px); margin: .9rem auto 0; }
    .gallery-crop-control-row { display: flex; align-items: center; gap: .65rem; }
    .gallery-crop-control-row input[type="range"] { flex: 1; min-width: 0; }
    .gallery-crop-hint { width: min(100%, 600px); margin: .65rem auto 0; color: var(--muted); font-size: .85rem; }
    .gallery-crop-error { min-height: 1.25rem; color: #a12c24; font-size: .88rem; }
    body.gallery-crop-open { overflow: hidden; }
    @media (max-width: 575.98px) {
        .gallery-upload { max-width: none; padding: 1rem; }
        .gallery-crop-modal { padding: .5rem; }
        .gallery-crop-dialog { max-height: 96vh; padding: .9rem; border-radius: .75rem; }
        .gallery-crop-footer .btn { flex: 1 1 auto; }
    }
</style>

<div class="gallery-crop-modal" id="galleryCropModal" hidden>
    <section class="gallery-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="galleryCropTitle" aria-describedby="galleryCropHint">
        <h2 class="h5 mb-1" id="galleryCropTitle">Adjust your image</h2>
        <p class="small text-muted mb-3">Move the image to choose what appears in the Gallery. The preview is 4:3.</p>
        <div class="gallery-crop-stage" id="galleryCropStage">
            <img id="galleryCropImage" alt="Image being cropped">
        </div>
        <div class="gallery-crop-controls">
            <label class="form-label mb-1" for="galleryCropZoom">Zoom</label>
            <div class="gallery-crop-control-row">
                <button class="btn btn-outline-secondary" type="button" id="galleryCropZoomOut" aria-label="Zoom out">−</button>
                <input type="range" id="galleryCropZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom image">
                <button class="btn btn-outline-secondary" type="button" id="galleryCropZoomIn" aria-label="Zoom in">+</button>
            </div>
        </div>
        <p class="gallery-crop-hint" id="galleryCropHint">Drag the image to reposition it. Use the zoom control for a closer crop.</p>
        <div class="gallery-crop-error" id="galleryCropError" role="status" aria-live="polite"></div>
        <div class="gallery-crop-footer d-flex flex-wrap justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" type="button" id="galleryCropCancel">Cancel</button>
            <button class="btn luxury-btn" type="button" id="galleryCropApply">Apply Crop</button>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('.gallery-edit-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
        const body = document.getElementById(btn.getAttribute('aria-controls'));
        if (!body) return;
        const willOpen = body.hasAttribute('hidden');
        body.toggleAttribute('hidden', !willOpen);
        btn.setAttribute('aria-expanded', String(willOpen));
    });
});
document.querySelectorAll('.gallery-edit-cancel').forEach((btn) => {
    btn.addEventListener('click', () => {
        const body = btn.closest('.gallery-admin-editor-body');
        if (!body) return;
        body.setAttribute('hidden', '');
        const toggle = document.querySelector(`.gallery-edit-toggle[aria-controls="${body.id}"]`);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
});

(() => {
    const modal = document.getElementById('galleryCropModal');
    const stage = document.getElementById('galleryCropStage');
    const image = document.getElementById('galleryCropImage');
    const zoom = document.getElementById('galleryCropZoom');
    const error = document.getElementById('galleryCropError');
    const applyButton = document.getElementById('galleryCropApply');
    const cancelButton = document.getElementById('galleryCropCancel');
    let activeInput = null;
    let sourceUrl = null;
    let resultUrl = null;
    let lastFocusedElement = null;
    let scale = 1;
    let baseScale = 1;
    let panX = 0;
    let panY = 0;
    let dragStart = null;

    function renderImage() {
        const bounds = stage.getBoundingClientRect();
        const imageWidth = image.naturalWidth * baseScale * scale;
        const imageHeight = image.naturalHeight * baseScale * scale;
        panX = Math.max((bounds.width - imageWidth) / 2, Math.min((imageWidth - bounds.width) / 2, panX));
        panY = Math.max((bounds.height - imageHeight) / 2, Math.min((imageHeight - bounds.height) / 2, panY));
        image.style.width = `${imageWidth}px`;
        image.style.height = `${imageHeight}px`;
        image.style.left = `${(bounds.width - imageWidth) / 2 + panX}px`;
        image.style.top = `${(bounds.height - imageHeight) / 2 + panY}px`;
    }

    function closeEditor(cancelSelection) {
        modal.hidden = true;
        document.body.classList.remove('gallery-crop-open');
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
        if (cancelSelection && activeInput) {
            activeInput.value = '';
            activeInput.dataset.cropReady = 'false';
            activeInput.closest('.mb-3, form')?.querySelector('[data-crop-result]')?.setAttribute('hidden', '');
        }
        activeInput = null;
        lastFocusedElement?.focus();
        lastFocusedElement = null;
    }

    function openEditor(input, file) {
        if (!file) return;
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        activeInput = input;
        error.textContent = '';
        scale = 1;
        panX = 0;
        panY = 0;
        zoom.value = '1';
        lastFocusedElement = document.activeElement;
        sourceUrl = URL.createObjectURL(file);
        image.onload = () => {
            const bounds = stage.getBoundingClientRect();
            baseScale = Math.max(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
            renderImage();
        };
        image.onerror = () => {
            error.textContent = 'This image could not be opened. Please choose another image.';
        };
        image.src = sourceUrl;
        modal.hidden = false;
        document.body.classList.add('gallery-crop-open');
        cancelButton.focus();
    }

    document.querySelectorAll('[data-gallery-crop-input]').forEach((input) => {
        input.addEventListener('change', () => {
            input.dataset.cropReady = 'false';
            const result = input.closest('.mb-3, form')?.querySelector('[data-crop-result]');
            if (result) result.hidden = true;
            openEditor(input, input.files?.[0]);
        });

        input.form.addEventListener('submit', (event) => {
            if (input.files?.length && input.dataset.cropReady !== 'true') {
                event.preventDefault();
                openEditor(input, input.files[0]);
            }
        });
    });

    zoom.addEventListener('input', () => {
        const previousScale = scale;
        scale = Number(zoom.value);
        const ratio = scale / previousScale;
        panX *= ratio;
        panY *= ratio;
        renderImage();
    });
    document.getElementById('galleryCropZoomOut').addEventListener('click', () => {
        zoom.value = String(Math.max(1, Number(zoom.value) - 0.15));
        zoom.dispatchEvent(new Event('input'));
    });
    document.getElementById('galleryCropZoomIn').addEventListener('click', () => {
        zoom.value = String(Math.min(3, Number(zoom.value) + 0.15));
        zoom.dispatchEvent(new Event('input'));
    });
    stage.addEventListener('pointerdown', (event) => {
        dragStart = { x: event.clientX, y: event.clientY, panX, panY };
        stage.setPointerCapture(event.pointerId);
    });
    stage.addEventListener('pointermove', (event) => {
        if (!dragStart) return;
        panX = dragStart.panX + event.clientX - dragStart.x;
        panY = dragStart.panY + event.clientY - dragStart.y;
        renderImage();
    });
    stage.addEventListener('pointerup', () => { dragStart = null; });
    stage.addEventListener('pointercancel', () => { dragStart = null; });

    cancelButton.addEventListener('click', () => closeEditor(true));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeEditor(true);
    });
    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            closeEditor(true);
            return;
        }
        if (event.key === 'Tab') {
            const focusable = [...modal.querySelectorAll('button:not(:disabled), input:not(:disabled)')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    applyButton.addEventListener('click', () => {
        if (!activeInput || !image.naturalWidth) return;
        error.textContent = '';
        const bounds = stage.getBoundingClientRect();
        const renderedWidth = image.naturalWidth * baseScale * scale;
        const renderedHeight = image.naturalHeight * baseScale * scale;
        const left = (bounds.width - renderedWidth) / 2 + panX;
        const top = (bounds.height - renderedHeight) / 2 + panY;
        const sourceX = Math.max(0, -left / (baseScale * scale));
        const sourceY = Math.max(0, -top / (baseScale * scale));
        const sourceWidth = bounds.width / (baseScale * scale);
        const sourceHeight = bounds.height / (baseScale * scale);
        const maxSide = 2000;
        const outputScale = Math.min(1, maxSide / sourceWidth, maxSide / sourceHeight);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(sourceWidth * outputScale));
        canvas.height = Math.max(1, Math.round(sourceHeight * outputScale));
        const context = canvas.getContext('2d');
        if (!context) {
            error.textContent = 'Your browser could not prepare the crop. Please try another browser.';
            return;
        }
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                error.textContent = 'The crop could not be created. Please try again.';
                return;
            }
            if (blob.size > 5 * 1024 * 1024) {
                error.textContent = 'The cropped image is still over 5 MB. Zoom in further or choose a smaller image.';
                return;
            }
            const croppedFile = new File([blob], 'gallery-crop.jpg', { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            activeInput.files = transfer.files;
            activeInput.dataset.cropReady = 'true';
            const result = activeInput.closest('.mb-3, form')?.querySelector('[data-crop-result]');
            if (result) {
                if (resultUrl) URL.revokeObjectURL(resultUrl);
                resultUrl = URL.createObjectURL(blob);
                result.querySelector('img').src = resultUrl;
                result.hidden = false;
            }
            closeEditor(false);
        }, 'image/jpeg', 0.92);
    });

    window.addEventListener('resize', () => {
        if (!modal.hidden && image.naturalWidth) {
            const bounds = stage.getBoundingClientRect();
            baseScale = Math.max(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
            renderImage();
        }
    });
})();
</script>
@endsection
