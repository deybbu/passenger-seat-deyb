document.addEventListener('DOMContentLoaded', () => {
    const ticket = document.querySelector('[data-event-ticket]');
    const eventForm = document.querySelector('#event-inquiry-form, .landing-event-form');
    const eventType = eventForm?.querySelector('[name="event_type"]');
    const otherEventType = eventForm?.querySelector('[name="event_type_other"]');
    const otherEventField = eventForm?.querySelector('[data-other-event-field]');
    const selectedDateLabel = document.querySelector('#event-date-display');

    const setTicketValue = (name, value, fallback) => {
        ticket?.querySelectorAll(`[data-ticket-value="${name}"]`).forEach((target) => {
            target.textContent = value || fallback;
        });
    };

    const updateTicket = () => {
        if (!ticket || !eventForm) return;

        const fieldValue = (name) => eventForm.querySelector(`[name="${name}"]`)?.value.trim() || '';
        const firstName = fieldValue('first_name');
        const lastName = fieldValue('last_name');
        const passenger = `${firstName} ${lastName}`.trim();
        const selectedEventType = fieldValue('event_type');
        const customEventType = fieldValue('event_type_other');
        const eventLabel = selectedEventType === 'Other' ? customEventType : selectedEventType;
        const rawDate = document.querySelector('#event-date')?.value;
        const dateParts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(rawDate || '');
        const dateLabel = dateParts
            ? new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                .format(new Date(Number(dateParts[1]), Number(dateParts[2]) - 1, Number(dateParts[3])))
            : '';
        const preferredTime = fieldValue('preferred_time');
        const preferredTimeFrom = fieldValue('preferred_time_from');
        const preferredTimeTo = fieldValue('preferred_time_to');
        const guestCount = fieldValue('guest_count');
        const selectedPackageOption = eventForm.querySelector('[name="event_package_id"]')?.selectedOptions[0];
        const selectedPackage = selectedPackageOption?.value
            ? (selectedPackageOption.dataset.name || selectedPackageOption.textContent.trim())
            : '';
        const packageName = selectedPackage || fieldValue('preferred_package');
        const email = fieldValue('email');
        const mobile = fieldValue('mobile_number');
        const dietary = fieldValue('dietary_restrictions');
        const requests = fieldValue('special_requests');

        setTicketValue('passenger', passenger, 'YOUR NAME');
        setTicketValue('passenger-stub', passenger, 'YOUR NAME');
        setTicketValue('event', eventLabel, 'SELECT EVENT');
        setTicketValue('event-stub', eventLabel, 'YOUR EVENT');
        setTicketValue('date', dateLabel, 'SELECT DATE');
        setTicketValue('date-stub', dateLabel, 'SELECT DATE');
        const timeRange = preferredTimeFrom && preferredTimeTo
            ? `${preferredTimeFrom} to ${preferredTimeTo}`
            : preferredTime;
        setTicketValue('time', timeRange, 'SELECT TIME');
        setTicketValue('time-stub', timeRange, 'SELECT TIME');
        setTicketValue('guests', guestCount ? `${guestCount} PAX` : '', 'NOT SET');
        setTicketValue('guests-stub', guestCount ? `${guestCount} PAX` : '', 'NOT SET');
        setTicketValue('package', packageName, 'NOT SET');
        setTicketValue('contact', [email, mobile].filter(Boolean).join(' / '), 'EMAIL / MOBILE');
        setTicketValue('email', email, 'NOT PROVIDED');
        setTicketValue('mobile', mobile, 'NOT PROVIDED');
        setTicketValue('dietary', dietary, 'NONE PROVIDED');
        setTicketValue('requests', requests, 'NONE PROVIDED');
    };

    const updateOtherEventField = () => {
        if (!eventType || !otherEventType || !otherEventField) return;

        const isOther = eventType.value === 'Other';
        otherEventField.hidden = !isOther;
        otherEventType.required = isOther;
        updateTicket();
    };

    eventForm?.querySelectorAll('[data-ticket-input]').forEach((input) => {
        input.addEventListener('input', updateTicket);
        input.addEventListener('change', updateTicket);
    });

    const guestCountField = eventForm?.querySelector('[name="guest_count"]');
    const packageSelect = eventForm?.querySelector('[name="event_package_id"]');
    const packageCapacityHint = eventForm?.querySelector('#package-capacity-hint');
    const updateEligiblePackages = () => {
        if (!guestCountField || !packageSelect) return;

        if (Number(guestCountField.value) > 30) guestCountField.value = '30';
        const guestCount = Number(guestCountField.value);
        const validCount = Number.isInteger(guestCount) && guestCount >= 1 && guestCount <= 30;
        const tierOptions = [...packageSelect.options].filter((option) => option.value !== '');
        let eligibleCount = 0;

        tierOptions.forEach((option) => {
            const min = Number(option.dataset.minGuests);
            const max = Number(option.dataset.maxGuests);
            const eligible = validCount && guestCount >= min && guestCount <= max;
            option.hidden = !eligible;
            if (eligible) eligibleCount += 1;
        });

        const selected = packageSelect.selectedOptions[0];
        if (selected?.value && selected.hidden) packageSelect.value = '';
        packageSelect.disabled = !validCount || eligibleCount === 0;
        packageSelect.required = eligibleCount > 0;
        if (packageCapacityHint) {
            packageCapacityHint.textContent = !validCount
                ? 'Enter a guest count from 1 to 30 to see eligible packages.'
                : eligibleCount
                    ? `Showing packages that fit ${guestCount} guests.`
                    : 'No tiered package is available for fewer than 10 guests.';
        }
        updateTicket();
    };

    guestCountField?.addEventListener('input', updateEligiblePackages);
    guestCountField?.addEventListener('change', updateEligiblePackages);
    updateEligiblePackages();

    const timeFrom = eventForm?.querySelector('[name="preferred_time_from"]');
    const timeTo = eventForm?.querySelector('[name="preferred_time_to"]');
    const updateTimeRange = () => {
        if (!timeFrom || !timeTo) return;

        const startIndex = [...timeFrom.options].findIndex((option) => option.value === timeFrom.value);

        [...timeTo.options].forEach((option, index) => {
            option.disabled = Boolean(option.value) && startIndex >= 0 && index <= startIndex;
        });

        if (timeTo.selectedOptions[0]?.disabled) timeTo.value = '';
        updateTicket();
    };

    timeFrom?.addEventListener('change', updateTimeRange);
    updateTimeRange();
    eventType?.addEventListener('change', updateOtherEventField);
    updateOtherEventField();

    const calendar = document.querySelector('[data-event-calendar]');

    if (!calendar) return;

    const grid = calendar.querySelector('[data-calendar-grid]');
    const monthLabel = calendar.querySelector('[data-calendar-month]');
    const selectedDate = document.querySelector('#event-date');
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
        updateTicket();
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
            const statusLabel = status === 'pending' || status === 'booked' || status === 'blocked'
                ? document.createElement('small')
                : null;

            button.type = 'button';
            button.className = `d ${status} landing-calendar-day is-${status}`;
            button.textContent = String(day);

            if (statusLabel) {
                statusLabel.textContent = status === 'pending' ? 'Pending' : status === 'blocked' ? 'Unavailable' : 'Booked';
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
        updateTicket();
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
