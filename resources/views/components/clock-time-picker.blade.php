@php($pickerId = 'clock-picker-'.$inputId)
<div class="clock-time-field" data-clock-time-picker>
    <label class="form-label" for="{{ $pickerId }}-toggle">{{ $label }}</label>
    <input id="{{ $inputId }}" data-clock-time-input name="{{ $name }}" type="time" class="form-control" value="{{ $value }}" required>
    <div class="clock-time-picker" hidden>
        <button type="button" class="form-control clock-time-toggle" id="{{ $pickerId }}-toggle" aria-haspopup="dialog" aria-expanded="false" aria-required="true">
            <span class="clock-time-value">Select a time</span><span class="clock-time-icon" aria-hidden="true"></span>
        </button>
        <section class="clock-time-panel" role="dialog" aria-label="Select {{ strtolower($label) }}" hidden>
            <div class="clock-time-header">
                <div class="clock-time-readout">
                    <button type="button" class="clock-time-hour" aria-label="Choose hour">12</button>
                    <span>:</span>
                    <button type="button" class="clock-time-minute" aria-label="Choose minutes">00</button>
                </div>
                <div class="clock-time-period" role="group" aria-label="AM or PM">
                    <button type="button" data-clock-period="AM" aria-pressed="true">AM</button>
                    <button type="button" data-clock-period="PM" aria-pressed="false">PM</button>
                </div>
            </div>
            <div class="clock-time-face" role="group" aria-label="Choose hour">
                <div class="clock-time-hand"></div>
                <div class="clock-time-numbers"></div>
            </div>
            <div class="clock-time-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm clock-time-cancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm clock-time-apply">Use this time</button>
            </div>
        </section>
    </div>
    <small class="form-text text-danger clock-time-error" hidden>Select a time to continue.</small>
    <small class="form-text">Choose a time using the clock.</small>
</div>

@once
<style>
    .clock-time-field { position: relative; z-index: 2; }
    .clock-time-picker[hidden], .clock-time-panel[hidden], .clock-time-error[hidden] { display: none !important; }
    .clock-time-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; }
    .clock-time-icon { position: relative; width: 18px; height: 18px; flex: 0 0 18px; border: 1.5px solid currentColor; border-radius: 50%; }
    .clock-time-icon:before, .clock-time-icon:after { position: absolute; left: 50%; top: 50%; width: 1.5px; background: currentColor; content: ''; transform-origin: 50% 0; }
    .clock-time-icon:before { height: 5px; transform: translate(-50%, -1px); }
    .clock-time-icon:after { height: 4px; transform: translate(-50%, -1px) rotate(120deg); }
    .clock-time-panel { position: fixed; left: 16px; top: 16px; z-index: 1080; width: min(320px, calc(100vw - 2rem)); max-height: min(390px, 65vh); overflow-y: auto; padding: 1rem; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); color: var(--ink); box-shadow: 0 18px 42px rgba(32, 32, 29, .2); }
    .clock-time-header { display: flex; align-items: center; justify-content: center; gap: .75rem; margin-bottom: .8rem; }
    .clock-time-readout { display: flex; align-items: center; gap: .15rem; color: var(--wine); font-size: 1.65rem; font-weight: 700; }
    .clock-time-readout button { min-width: 2.5rem; padding: .2rem .3rem; border: 0; border-radius: 6px; background: transparent; color: inherit; font: inherit; }
    .clock-time-readout button[aria-pressed="true"], .clock-time-readout button:hover { background: var(--mint); }
    .clock-time-period { display: grid; gap: .18rem; }
    .clock-time-period button { padding: .15rem .4rem; border: 1px solid var(--line); border-radius: 5px; background: var(--surface); color: var(--muted); font-size: .68rem; font-weight: 700; }
    .clock-time-period button[aria-pressed="true"] { border-color: var(--wine); background: var(--wine); color: #fff; }
    .clock-time-face { --clock-number-radius: 72px; position: relative; width: min(230px, 100%); aspect-ratio: 1; margin: 0 auto .85rem; border-radius: 50%; background: var(--mint); }
    .clock-time-hand { position: absolute; left: calc(50% - 1px); top: 50%; z-index: 0; width: 2px; height: 37%; border-radius: 2px; background: var(--terracotta); transform: rotate(180deg); transform-origin: 50% 0; pointer-events: none; }
    .clock-time-hand:after { position: absolute; left: 50%; bottom: -4px; width: 9px; height: 9px; border-radius: 50%; background: var(--wine); content: ''; transform: translateX(-50%); }
    .clock-time-number { position: absolute; left: 50%; top: 50%; z-index: 1; display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: var(--ink); font-size: .83rem; font-weight: 700; transform: translate(-50%, -50%) rotate(var(--clock-angle)) translateY(calc(-1 * var(--clock-number-radius))) rotate(calc(var(--clock-angle) * -1)); }
    .clock-time-number:hover, .clock-time-number:focus-visible, .clock-time-number[aria-pressed="true"] { outline: 0; background: var(--wine); color: #fff; }
    .clock-time-actions { display: flex; justify-content: flex-end; gap: .5rem; }
    body.dark-mode .clock-time-panel { border-color: var(--line); background: var(--surface); color: var(--ink); }
    body.dark-mode .clock-time-number { color: var(--ink); }
    body.dark-mode .clock-time-number:hover, body.dark-mode .clock-time-number:focus-visible, body.dark-mode .clock-time-number[aria-pressed="true"] { background: var(--terracotta); color: #fff; }
    @media(max-width:575px) { .clock-time-face { width: min(210px, 100%); } }
</style>
<script>
(() => {
    document.querySelectorAll('[data-clock-time-picker]').forEach((root) => {
        const input = root.querySelector('[data-clock-time-input]');
        const picker = root.querySelector('.clock-time-picker');
        const toggle = root.querySelector('.clock-time-toggle');
        const panel = root.querySelector('.clock-time-panel');
        const face = root.querySelector('.clock-time-face');
        const numbers = root.querySelector('.clock-time-numbers');
        const hand = root.querySelector('.clock-time-hand');
        const hourOutput = root.querySelector('.clock-time-hour');
        const minuteOutput = root.querySelector('.clock-time-minute');
        const valueOutput = root.querySelector('.clock-time-value');
        const errorOutput = root.querySelector('.clock-time-error');
        let selectedHour = 12;
        let selectedMinute = 0;
        let selectedPeriod = 'AM';
        let mode = 'hours';

        const updateReadout = () => {
            hourOutput.textContent = String(selectedHour);
            minuteOutput.textContent = String(selectedMinute).padStart(2, '0');
            root.querySelectorAll('[data-clock-period]').forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.clockPeriod === selectedPeriod));
            });
        };
        const renderFace = () => {
            const choosingHours = mode === 'hours';
            const values = choosingHours ? Array.from({ length: 12 }, (_, index) => index + 1) : Array.from({ length: 12 }, (_, index) => index * 5);
            const selectedValue = choosingHours ? selectedHour : selectedMinute;
            const angle = choosingHours ? (selectedHour % 12) * 30 : (selectedMinute / 5) * 30;
            face.setAttribute('aria-label', choosingHours ? 'Choose hour' : 'Choose minutes');
            hand.style.transform = `rotate(${180 + angle}deg)`;
            numbers.replaceChildren();

            values.forEach((value) => {
                const button = document.createElement('button');
                const numberAngle = choosingHours ? (value % 12) * 30 : (value / 5) * 30;
                button.type = 'button';
                button.className = 'clock-time-number';
                button.style.setProperty('--clock-angle', `${numberAngle}deg`);
                button.textContent = choosingHours ? String(value) : String(value).padStart(2, '0');
                button.setAttribute('aria-label', choosingHours ? `${value} o'clock` : `${String(value).padStart(2, '0')} minutes`);
                button.setAttribute('aria-pressed', String(value === selectedValue));
                button.addEventListener('click', () => {
                    if (choosingHours) {
                        selectedHour = value;
                        mode = 'minutes';
                    } else {
                        selectedMinute = value;
                    }
                    updateReadout();
                    renderFace();
                });
                numbers.append(button);
            });
        };
        const alignPanel = () => {
            const bounds = picker.getBoundingClientRect();
            const width = Math.min(320, window.innerWidth - 32);
            const height = panel.getBoundingClientRect().height;
            const left = Math.max(16, Math.min(bounds.left, window.innerWidth - width - 16));
            const below = window.innerHeight - bounds.bottom - 16;
            const maxTop = Math.max(16, window.innerHeight - height - 16);
            const preferred = below >= height + 6 ? bounds.bottom + 6 : bounds.top - height - 6;
            panel.style.left = `${left}px`;
            panel.style.top = `${Math.max(16, Math.min(preferred, maxTop))}px`;
        };
        const close = (returnFocus = false) => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) toggle.focus();
        };
        const open = () => {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            alignPanel();
            renderFace();
        };

        if (input.value) {
            const [hours, minutes] = input.value.split(':').map(Number);
            selectedHour = hours % 12 || 12;
            selectedMinute = minutes;
            selectedPeriod = hours < 12 ? 'AM' : 'PM';
            valueOutput.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
        }
        input.hidden = true;
        input.required = false;
        input.setAttribute('aria-hidden', 'true');
        input.tabIndex = -1;
        picker.hidden = false;
        updateReadout();
        renderFace();

        toggle.addEventListener('click', () => panel.hidden ? open() : close());
        hourOutput.addEventListener('click', () => { mode = 'hours'; renderFace(); });
        minuteOutput.addEventListener('click', () => { mode = 'minutes'; renderFace(); });
        root.querySelectorAll('[data-clock-period]').forEach((button) => button.addEventListener('click', () => {
            selectedPeriod = button.dataset.clockPeriod;
            updateReadout();
        }));
        root.querySelector('.clock-time-cancel').addEventListener('click', () => close(true));
        root.querySelector('.clock-time-apply').addEventListener('click', () => {
            const hours = (selectedHour % 12) + (selectedPeriod === 'PM' ? 12 : 0);
            input.value = `${String(hours).padStart(2, '0')}:${String(selectedMinute).padStart(2, '0')}`;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            valueOutput.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
            toggle.removeAttribute('aria-invalid');
            errorOutput.hidden = true;
            close(true);
        });
        root.closest('form')?.addEventListener('submit', (event) => {
            if (input.value) return;
            event.preventDefault();
            toggle.setAttribute('aria-invalid', 'true');
            errorOutput.hidden = false;
            toggle.focus();
        });
        document.addEventListener('pointerdown', (event) => {
            if (!root.contains(event.target)) close();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) close(true);
        });
        window.addEventListener('resize', () => { if (!panel.hidden) alignPanel(); });
        window.addEventListener('scroll', () => { if (!panel.hidden) alignPanel(); }, true);
    });
})();
</script>
@endonce