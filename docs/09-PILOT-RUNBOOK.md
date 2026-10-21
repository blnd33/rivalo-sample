# Pilot runbook

A pilot is one real, small event with real players, run end to end on the staging or production server. The software is ready to support it; **the event itself has to be organised and run by you and your staff.** Nothing here has happened yet.

Suggested shape: one free tournament (no entry fee while decision D7 is open), one sport, 8–16 entrants, one venue, one evening, invited players who agree to give feedback.

## Two weeks before

- [ ] Server set up from docs/07 (HTTPS, PostgreSQL, queue worker, scheduler cron, backups, `php artisan storage:link`).
- [ ] `php artisan rivalo:launch-check` shows **no FAIL**. Decide consciously about every WARN (for example "Phone notifications: inbox only" if Firebase isn't set up yet).
- [ ] No sample data on this server (`launch-check` fails if there is any).
- [ ] Super admin created with `php artisan rivalo:make-admin`; organizer and referee accounts created and promoted; each has signed in once to enrol their authenticator app.
- [ ] Policy pages: Community guidelines published. Terms and Privacy published. If legal review is not finished, the app shows "Draft: pending legal review". **Agree with the pilot players that the pilot runs on draft terms**, or wait for the review.
- [ ] Owner decisions for this event written down: discipline active (D9), points table published or no points for the pilot (D3), what happens if fewer than the minimum turn up (D5).
- [ ] Tournament created in the admin panel: rules, venue, times, capacity, check-in window. Organizer and referee assigned. Published, then registration opened.
- [ ] Support inbox (`RIVALO_SUPPORT_EMAIL`) is monitored during and after the event.
- [ ] Restore drill done (docs/07).

## One week before

- [ ] Players install the test build (TestFlight / Play internal testing) and register. Check that verification emails arrive (look in spam too).
- [ ] Watch registrations in the admin panel. The waitlist promotes automatically when someone cancels.
- [ ] Send an announcement to the tournament's entrants with the venue and time (Admin → Community → Announcements).
- [ ] Dry run with staff on their own phones: check-in desk (scan the player's QR code with the phone camera), entering a result, approving it, raising and resolving a dispute.

## On the day

| When | Who | What |
|---|---|---|
| 1 h before | Organizer | Open the check-in desk (Admin → Venue check-in). Players show the QR code from the app. Check in everyone who arrives. |
| Check-in closes | Organizer | Close registration if still open. Make the draw (Admin → Competition → Fixtures and results), review it, publish. Players get "The draw is out". |
| During | Players / referee | Players enter scores in the app or the referee enters them. The referee approves. The bracket fills in automatically. |
| Problems | Referee | Disputes appear on the dashboard under "Needs your attention". Resolve with a note. |
| Problems | Support | Reports about players appear under Community → Reports. Act with a reason; everything is audited. |
| End | Organizer | Confirm final placements. Points (if a real table is published) and badges are awarded from approved results only. |

Keep a written log of anything unexpected (time, what happened, who handled it). It becomes the pilot report.

## After the event (within a week)

- [ ] Send an announcement thanking players and asking for feedback through **Settings → Report a problem or send feedback** in the app. Feedback lands in Community → Reports (type "App").
- [ ] Review every report and piece of feedback. Mark each one handled.
- [ ] Check the dashboard counts against what happened on the day (entrants, check-ins, results).
- [ ] If any money changed hands (it shouldn't while entry is free), export the finance report and reconcile it with the cash.
- [ ] Write the pilot report: what worked, what broke, what players asked for, what staff found slow. Decide what must change before the next event or the public launch.

## What to measure (from real data only)

Registered vs. checked in; how long check-in took; matches with results entered by players vs. by the referee; time from match end to approval; disputes and how they ended; reports filed; app problems reported; how many players registered for a second event. Don't publish these as adoption figures until they come from more than the pilot.
