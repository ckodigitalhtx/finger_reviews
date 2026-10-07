/* Star selection and dynamic panel logic for the public review page. */
(function () {
    'use strict';

    var starsWrap = document.getElementById('stars');
    if (!starsWrap) { return; }

    var stars         = Array.prototype.slice.call(starsWrap.querySelectorAll('.star'));
    var label         = document.getElementById('rating-label');
    var panelPositive = document.getElementById('panel-positive');
    var panelNegative = document.getElementById('panel-negative');
    var ratingInput   = document.getElementById('star_rating');
    var labels        = ['', 'Poor', 'Fair', 'Average', 'Good', 'Excellent'];
    var selected      = 0;

    function paint(upTo) {
        stars.forEach(function (star, index) {
            star.classList.toggle('is-on', index < upTo);
        });
        label.textContent = labels[upTo] || ' ';
    }

    function select(value, moveFocus) {
        selected = value;
        paint(value);

        stars.forEach(function (star, index) {
            var isChecked = index === value - 1;
            star.setAttribute('aria-checked', isChecked ? 'true' : 'false');
            star.tabIndex = isChecked ? 0 : -1;
        });

        var isPositive = value >= 4;
        panelPositive.hidden = !isPositive;
        panelNegative.hidden = isPositive;
        if (ratingInput) { ratingInput.value = isPositive ? '' : value; }

        if (moveFocus) {
            var panel = isPositive ? panelPositive : panelNegative;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    stars.forEach(function (star, index) {
        var value = index + 1;
        star.tabIndex = index === 0 ? 0 : -1;

        star.addEventListener('mouseenter', function () { paint(value); });
        star.addEventListener('focus', function () { paint(value); });
        star.addEventListener('blur', function () { paint(selected); });
        star.addEventListener('click', function () { select(value, true); });

        // Arrow-key support, like a native radio group
        star.addEventListener('keydown', function (event) {
            var next = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowUp') { next = Math.min(5, value + 1); }
            if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') { next = Math.max(1, value - 1); }
            if (next !== null) {
                event.preventDefault();
                stars[next - 1].focus();
                select(next, false);
            }
        });
    });

    starsWrap.addEventListener('mouseleave', function () { paint(selected); });

    // Restore the rating after a validation error round-trip
    var initial = parseInt(starsWrap.getAttribute('data-initial'), 10);
    if (initial >= 1 && initial <= 5) { select(initial, false); }

    // Guard against double submits
    var form = document.getElementById('feedback-form');
    if (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.textContent = 'Sending…'; }
        });
    }
})();
