(function () {
    var modal = document.getElementById('tnr-registration-onboarding-modal');
    if (!modal) return;

    var form = document.getElementById('tnr-onboarding-form');
    var steps = Array.from(modal.querySelectorAll('[data-ob-step]'));
    var dots = Array.from(modal.querySelectorAll('[data-ob-dot]'));
    var backBtn = document.getElementById('ob-back-btn');
    var nextBtn = document.getElementById('ob-next-btn');
    var medEditor = document.getElementById('ob-med-editor');
    var medAdd = document.getElementById('ob-med-add');
    var current = 0;

    function showStep(index) {
        current = index;
        steps.forEach(function (step, i) {
            step.classList.toggle('is-active', i === index);
        });
        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === index);
            dot.classList.toggle('is-done', i < index);
        });
        if (backBtn) backBtn.disabled = index === 0;
        if (nextBtn) {
            var isLast = index === steps.length - 1;
            nextBtn.textContent = isLast ? 'Complete' : 'Next';
            nextBtn.type = isLast ? 'submit' : 'button';
        }
    }

    function bindOptionGroup(name) {
        var inputs = form.querySelectorAll('input[name="' + name + '"]');
        inputs.forEach(function (input) {
            input.addEventListener('change', function () {
                modal.querySelectorAll('[data-ob-option="' + name + '"]').forEach(function (btn) {
                    btn.classList.toggle('is-selected', btn.dataset.obValue === input.value);
                });
            });
        });
        modal.querySelectorAll('[data-ob-option="' + name + '"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.dataset.obValue;
                var input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
                if (input) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    }

    ['therapist_gender_preference', 'is_pregnant', 'pressure_preference'].forEach(bindOptionGroup);

    function validateStep(index) {
        var step = steps[index];
        if (!step) return true;
        var required = step.querySelectorAll('[data-ob-required]');
        for (var i = 0; i < required.length; i++) {
            var name = required[i].getAttribute('name');
            if (!name) continue;
            var checked = form.querySelector('input[name="' + name + '"]:checked');
            if (!checked) return false;
        }
        return true;
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            if (current > 0) showStep(current - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function (e) {
            if (!validateStep(current)) {
                e.preventDefault();
                alert('Please answer this question before continuing.');
                return;
            }
            if (current < steps.length - 1) {
                e.preventDefault();
                showStep(current + 1);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            for (var i = 0; i < steps.length; i++) {
                if (!validateStep(i)) {
                    e.preventDefault();
                    showStep(i);
                    alert('Please answer all required questions before completing.');
                    return;
                }
            }
        });
    }

    if (medAdd && medEditor) {
        medAdd.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'ob-med-row';
            row.innerHTML =
                '<input type="text" name="medications[]" value="" placeholder="e.g. Ibuprofen (Advil)" maxlength="255">' +
                '<button type="button" class="ob-med-remove" data-ob-med-remove aria-label="Remove">&times;</button>';
            medEditor.appendChild(row);
            row.querySelector('input')?.focus();
            row.querySelector('[data-ob-med-remove]')?.addEventListener('click', function () {
                row.remove();
            });
        });
        medEditor.querySelectorAll('[data-ob-med-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.ob-med-row')?.remove();
            });
        });
    }

    modal.classList.remove('ob-hidden');
    document.body.classList.add('modal-open');
    showStep(0);
})();
