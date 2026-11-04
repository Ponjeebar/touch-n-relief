(function () {
    const code = document.getElementById('verification-code');
    const group = document.querySelector('[data-code-digits]');
    if (!code || !group) return;

    const digits = Array.from(group.querySelectorAll('input'));
    const normalize = value => value.replace(/[^0-9]/g, '');
    const sync = () => { code.value = digits.map(input => input.value).join(''); };
    function focusDigit(index) {
        digits[index].focus();
        digits[index].select();
    }
    function distribute(value, index) {
        const numbers = normalize(value).slice(0, 6);
        if (!numbers) return;
        const start = numbers.length === 6 ? 0 : index;
        for (let offset = 0; offset < numbers.length && start + offset < 6; offset++) {
            digits[start + offset].value = numbers[offset];
        }
        sync();
        focusDigit(Math.min(start + numbers.length, 5));
    }
    digits.forEach((input, index) => {
        input.required = true;
        input.addEventListener('focus', () => input.select());
        input.addEventListener('beforeinput', event => {
            if (event.data && event.data.length > 1) {
                event.preventDefault();
                distribute(event.data, index);
            }
        });
        input.addEventListener('input', () => {
            const value = normalize(input.value);
            input.value = '';
            if (value) distribute(value, index);
            else sync();
        });
        input.addEventListener('paste', event => {
            event.preventDefault();
            distribute(event.clipboardData.getData('text'), index);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                event.preventDefault();
                digits[index - 1].value = '';
                sync();
                focusDigit(index - 1);
            } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusDigit(index - 1);
            } else if (event.key === 'ArrowRight' && index < 5) {
                event.preventDefault();
                focusDigit(index + 1);
            }
        });
    });

    document.querySelector('[data-code-fallback-label]').hidden = true;
    code.type = 'hidden';
    code.removeAttribute('autofocus');
    group.hidden = false;
    if (code.value) distribute(code.value, 0);
    else focusDigit(0);
})();
