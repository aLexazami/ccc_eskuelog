/**
 * eSkueLog - Live Clock & Date Module
 * Formats time (12-hour format with AM/PM) and date (DDD | MMMM D, YYYY)
 */
(function () {
    'use strict';

    function updateLiveClock() {
        const now = new Date();

        // 1. Hours, Minutes, Seconds formatting (12-Hour Clock)
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const period = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? hours : 12; // Convert '0' to '12'
        const formattedHours = String(hours).padStart(2, '0');

        // 2. Custom Date Formatting: "Thu | August 7, 2026"
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const dayName = days[now.getDay()];
        const monthName = months[now.getMonth()];
        const dayNum = now.getDate();
        const year = now.getFullYear();

        const formattedDate = `${dayName} | ${monthName} ${dayNum}, ${year}`;

        // 3. Target DOM Elements safely
        const clockTimeEl = document.getElementById('clockTime');
        const clockPeriodEl = document.getElementById('clockPeriod');
        const clockDateEl = document.getElementById('clockDate');

        if (clockTimeEl) clockTimeEl.textContent = `${formattedHours}:${minutes}:${seconds}`;
        if (clockPeriodEl) clockPeriodEl.textContent = period;
        if (clockDateEl) clockDateEl.textContent = formattedDate;
    }

    // Initialize clock when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', () => {
        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    });
})();