<div class="ls-field">
    <div class="system-field-copy">
        <label for="{{ $name }}">{{ $label }}</label>
        <p class="ls-field-help" id="{{ $name }}_help">{{ $help }}</p>
    </div>
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
            aria-describedby="{{ $name }}_help{{ $errors->has($name) ? ' '.$name.'_error' : '' }}"
            @if($errors->has($name)) aria-invalid="true" @endif
        >
        <span>{{ $unit }}</span>
    </div>
    @error($name)<span class="field-error" id="{{ $name }}_error">{{ $message }}</span>@enderror
</div>
