document.addEventListener('DOMContentLoaded', () => {
    const calendar = document.querySelector('[data-event-calendar]');

    if (!calendar) return;

    const grid = calendar.querySelector('[data-calendar-grid]');
    const monthLabel = calendar.querySelector('[data-calendar-month]');
    const selectedDate = document.querySelector('#event-date');
    const selectedDateLabel = document.querySelector('#event-date-display');
    const dateError = document.querySelector('#event-date-error');
    const availability = JSON.parse(calendar.dataset.availability || '{}');
    const today = parseDate(calendar.dataset.today);
    const initialDate = parseDate(selectedDate.value) || today;
    let displayedYear = initialDate.getFullYear();
    let displayedMonth = initialDate.getMonth();

    function parseDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

        if (!match) return null;

        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function formatKey(year, month, day) {
        return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    function updateSelectedDate(dateKey) {
        const date = parseDate(dateKey);

        selectedDate.value = dateKey;
        selectedDateLabel.value = new Intl.DateTimeFormat('en-PH', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
        selectedDateLabel.removeAttribute('aria-invalid');
        dateError.textContent = '';
        renderCalendar();
    }

    function renderCalendar() {
        const firstDay = new Date(displayedYear, displayedMonth, 1).getDay();
        const daysInMonth = new Date(displayedYear, displayedMonth + 1, 0).getDate();
        const selectedKey = selectedDate.value;
        const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

        monthLabel.textContent = new Intl.DateTimeFormat(undefined, {
            month: 'long',
            year: 'numeric',
        }).format(new Date(displayedYear, displayedMonth, 1));

        grid.replaceChildren();

        weekdays.forEach((weekday) => {
            const heading = document.createElement('span');
            heading.className = 'landing-calendar-weekday';
            heading.setAttribute('role', 'columnheader');
            heading.textContent = weekday;
            grid.append(heading);
        });

        for (let blank = 0; blank < firstDay; blank += 1) {
            const spacer = document.createElement('span');
            spacer.className = 'landing-calendar-empty';
            spacer.setAttribute('aria-hidden', 'true');
            grid.append(spacer);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const dateKey = formatKey(displayedYear, displayedMonth, day);
            const date = new Date(displayedYear, displayedMonth, day);
            const isPast = date < today;
            const status = isPast ? 'past' : (availability[dateKey] || 'available');
            const button = document.createElement('button');
            const statusLabel = status === 'pending' || status === 'booked'
                ? document.createElement('small')
                : null;

            button.type = 'button';
            button.className = `d ${status} landing-calendar-day is-${status}`;
            button.textContent = String(day);

            if (statusLabel) {
                statusLabel.textContent = status === 'pending' ? 'Pending' : 'Booked';
                button.append(statusLabel);
            }
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', `${new Intl.DateTimeFormat(undefined, {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            }).format(date)}, ${isPast ? 'unavailable, past date' : status}`);
            button.setAttribute('aria-pressed', String(selectedKey === dateKey));
            button.disabled = status !== 'available';

            if (selectedKey === dateKey) button.classList.add('is-selected');

            button.addEventListener('click', () => updateSelectedDate(dateKey));
            grid.append(button);
        }
    }

    calendar.querySelector('[data-calendar-previous]').addEventListener('click', () => {
        displayedMonth -= 1;

        if (displayedMonth < 0) {
            displayedMonth = 11;
            displayedYear -= 1;
        }

        renderCalendar();
    });

    calendar.querySelector('[data-calendar-next]').addEventListener('click', () => {
        displayedMonth += 1;

        if (displayedMonth > 11) {
            displayedMonth = 0;
            displayedYear += 1;
        }

        renderCalendar();
    });

    renderCalendar();
});
