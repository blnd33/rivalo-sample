/* ==========================================================================
   RIVALO — Coming Soon
   ========================================================================== */

/* --------------------------------------------------------------------------
   LAUNCH DATE SETTING
   --------------------------------------------------------------------------
   Leave as `null` until the launch date is officially confirmed.
   While it is null, the page shows "Launch date coming soon" and "—"
   in every countdown block.

   To start the countdown, replace `null` with an ISO 8601 date-time string
   that INCLUDES an explicit timezone offset (or "Z" for UTC):

     "YYYY-MM-DDTHH:MM:SS+HH:MM"

   Examples (illustrative only — not real launch dates):
     const LAUNCH_DATE = "2027-03-15T18:00:00+03:00"; // 6:00 PM at UTC+3
     const LAUNCH_DATE = "2027-03-15T15:00:00Z";      // 3:00 PM UTC

   A date without an offset (e.g. "2027-03-15T18:00:00") is rejected,
   because browsers would interpret it in each visitor's own time zone.
   -------------------------------------------------------------------------- */
const LAUNCH_DATE = null;


/* ==========================================================================
   Nothing below needs editing for normal use.
   ========================================================================== */

(function () {
  'use strict';

  const ISO_WITH_OFFSET = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,3})?)?(Z|[+-]\d{2}:\d{2})$/;

  const MS = { second: 1000, minute: 60000, hour: 3600000, day: 86400000 };

  const TEXT = {
    noDate: 'Launch date coming soon',
    complete: 'The countdown is complete. Stay tuned for our launch announcement.',
  };

  /* ---------- Countdown ---------- */

  /**
   * Returns a launch timestamp (ms) or null when the setting is empty/invalid.
   */
  function parseLaunchDate(value) {
    if (value === null || value === undefined || value === '') return null;

    if (typeof value !== 'string' || !ISO_WITH_OFFSET.test(value.trim())) {
      console.warn(
        '[Rivalo] LAUNCH_DATE must look like "2027-03-15T18:00:00+03:00" (with a timezone offset). ' +
        'Received:', value, '— showing "Launch date coming soon" instead.'
      );
      return null;
    }

    const time = Date.parse(value.trim());
    if (Number.isNaN(time)) {
      console.warn('[Rivalo] LAUNCH_DATE is not a valid date:', value);
      return null;
    }
    return time;
  }

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function initCountdown() {
    const section = document.getElementById('countdown');
    if (!section) return;

    const statusText = document.getElementById('countdown-status-text');
    const statusLine = document.getElementById('countdown-status');
    const timer = document.getElementById('countdown-timer');
    const complete = document.getElementById('countdown-complete');
    const note = document.getElementById('countdown-note');
    const fields = {
      days: timer.querySelector('[data-unit="days"]'),
      hours: timer.querySelector('[data-unit="hours"]'),
      minutes: timer.querySelector('[data-unit="minutes"]'),
      seconds: timer.querySelector('[data-unit="seconds"]'),
    };

    const target = parseLaunchDate(LAUNCH_DATE);
    let timeoutId = null;

    function setState(state) {
      section.dataset.state = state;
    }

    function showIdle() {
      setState('idle');
      statusText.textContent = TEXT.noDate;
      Object.values(fields).forEach(function (el) { el.textContent = '—'; });
      // The dashes carry no information; the status line already explains it.
      timer.setAttribute('aria-hidden', 'true');
    }

    function showComplete() {
      setState('complete');
      if (timeoutId) clearTimeout(timeoutId);
      statusLine.hidden = true;
      timer.hidden = true;
      note.hidden = true;
      complete.textContent = TEXT.complete;
      complete.hidden = false;
    }

    function render(remaining) {
      // Never show negative numbers.
      const ms = Math.max(0, remaining);
      const values = {
        days: Math.floor(ms / MS.day),
        hours: Math.floor((ms % MS.day) / MS.hour),
        minutes: Math.floor((ms % MS.hour) / MS.minute),
        seconds: Math.floor((ms % MS.minute) / MS.second),
      };
      Object.keys(fields).forEach(function (unit) {
        const text = pad(values[unit]);
        if (fields[unit].textContent !== text) fields[unit].textContent = text;
      });
    }

    function tick() {
      // Always measured from the real current time — no stored counter to drift.
      const remaining = target - Date.now();
      if (remaining <= 0) {
        showComplete();
        return;
      }
      render(remaining);
      // Schedule the next update right after the next whole second.
      timeoutId = setTimeout(tick, (remaining % MS.second) || MS.second);
    }

    if (target === null) {
      showIdle();
      return;
    }

    if (target - Date.now() <= 0) {
      showComplete();
      return;
    }

    // Counting state
    setState('counting');
    timer.removeAttribute('aria-hidden');
    note.hidden = false;
    try {
      const formatted = new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
      }).format(new Date(target));
      statusText.textContent = 'Counting down to ' + formatted;
    } catch (e) {
      statusText.textContent = 'Counting down to launch';
    }

    tick();

    // Re-sync instantly when a backgrounded tab becomes visible again.
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && section.dataset.state === 'counting') {
        clearTimeout(timeoutId);
        tick();
      }
    });
  }

  /* ---------- Sign-up preview: never collects anything ---------- */

  function initSignupPreview() {
    const form = document.getElementById('signup-form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
      event.preventDefault(); // static preview — no data is sent or stored
    });
  }

  /* ---------- Header border on scroll ---------- */

  function initHeader() {
    const header = document.getElementById('site-header');
    if (!header) return;
    let ticking = false;
    function update() {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    }, { passive: true });
    update();
  }

  /* ---------- Scroll reveal ---------- */

  function initReveal() {
    const items = document.querySelectorAll('[data-reveal]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    items.forEach(function (el) { observer.observe(el); });
  }

  /* ---------- Footer year ---------- */

  function initYear() {
    const year = document.getElementById('year');
    if (year) year.textContent = String(new Date().getFullYear());
  }

  /* ---------- Boot ---------- */

  function init() {
    initYear();
    initCountdown();
    initSignupPreview();
    initHeader();
    initReveal();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
