<div class="ls-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <div class="system-number-input">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="number"
            value="{{ old($name, $rules[$name]) }}"
            required
            min="{{ $min }}"
            max="{{ $max }}"
            step="1"
            inputmode="numeric"
        >
        <span>{{ $unit }}</span>
    </div>
    <p class="ls-field-help">{{ $help }}</p>
    @error($name)<span class="field-error">{{ $message }}</span>@enderror
</div>
