# Milestones

| # | Scope | Status |
|---|---|---|
| **M1** | Architecture, decisions register, database plan, screen map, design tokens, Flutter design system and app shell, Laravel and Filament scaffolding, branded admin theme | ✅ Delivered |
| **M2** | Backend auth (register, verify email, login, logout, logout-all, password reset), Sanctum device tokens, roles and permissions, staff MFA, sports catalog, profiles (public/private split), privacy, notification preferences, account deletion workflow, audit log, feature flags. Mobile auth, onboarding and profile screens. | ✅ Delivered |
| **M3** | Teams (create, invite, accept/decline, leave/remove, roster limits, history, captain transfer), seasons, tournaments (admin CRUD, staff assignment, status machine, paid-entry compliance gate), registration (individual/pair/team, capacity, waitlist, conflict prevention, roster snapshot, cancellation with waitlist promotion). Mobile compete and teams screens. | ✅ Delivered |
| M4A | QR check-in, staff-recorded cash payments with receipt numbers, voiding mistakes, refunds, duplicate prevention, eligibility | ✅ Delivered |
| M4B | Sponsors, contributions with restrictions, prize tiers and labels, tournament ledger, finance reports (finance rules are drafts until D4 is decided) | ✅ Delivered |
| Design + News | News (admin + API + app slider/feed/article) pulled forward from M6; visual redesign of Home, Compete, tournament pages, Rankings and Profile | ✅ Delivered |
| M5 | Stages, fixtures (single elimination, round robin, groups → knockout), match results, reviewer approval, disputes, bracket progression, points rule sets (drafts until published; D1–D3 open), append-only points ledger with idempotency and reversals, leaderboards | ✅ Delivered |
| M6 | Achievements, announcements, notification inbox, push provider, targeting and preferences (news already delivered) | ✅ Delivered (phone delivery waits for a Firebase project) |
| M7 | Admin polish, Arabic/Kurdish localization and RTL review, reports and moderation, policy pages, security testing, pilot | ✅ Delivered (native-speaker review, legal review and the pilot event itself are owner actions) |
| M8 | Official merchandise (catalog, variants, stock, cart, orders, pickup/delivery). Payment provider only after verification. | ✅ Delivered (cash on pickup/delivery; store off until `store_enabled` is switched on) |
| M9 | Approved seller marketplace | ✅ Delivered (consignment model D22; off until `marketplace_enabled` is switched on; commission and payout terms are per signed agreement) |

## Acceptance criteria per delivered milestone

### M1
- [x] `design-system/tokens.json` defines every brand color, the type scale, spacing, radii and motion. Flutter `tokens.dart` mirrors it.
- [x] The Flutter app compiles (`flutter analyze` clean, `flutter build web` OK) and shows the branded welcome screen. It was verified end to end against the local API in a phone-sized web preview.
- [ ] Run on an Android emulator or device. **Not yet verified on this machine** because the Android SDK command-line tools are missing (see setup guide). iOS requires macOS and Xcode.
- [x] The design-system gallery (debug builds only) shows every component in loading, empty, error, disabled and status variants.
- [x] The admin panel runs at `/admin` with the RIVALO dark theme.

### M2
- [x] `php artisan test` passes for authentication, verification, password reset, token revocation, permissions and private-field tests.
- [x] `GET /api/v1/users/{username}` never returns email, phone, privacy settings or moderation fields, and honors `show_display_name`.
- [x] Staff cannot open the admin panel without TOTP MFA enrolled.
- [x] Requesting account deletion schedules it with a 14-day grace period that the user can cancel. The scheduled command anonymizes the account and keeps tournament history.

### M9
- [x] Applications: players apply in the app (verified email; one pending application at a time; rate limited). Staff approve with the shop name, the commission and reference from the signed agreement, and a note on what they checked, after confirming it; or reject with a reason the applicant sees. Staff never review their own application or shop (tested).
- [x] No guessed money rules: there is no default commission. The rate is copied onto each order line at checkout, so changing it later never changes past sales (tested).
- [x] Listings: sellers create products in the Seller Hub (always a draft in the store currency), add sizes and photos (re-encoded), and submit. Staff approve, request changes or take a live listing off sale with a note; the seller gets an inbox message. Sellers edit only drafts or listings sent back; to change a live listing they take it off sale first.
- [x] Store: seller products appear only while the shop is active and `marketplace_enabled` is on; "Sold by" on cards, product page, bag and orders; shops rail and shop pages; official products are labelled as official. Public responses contain only the shop's slug, name, description and logo (tested).
- [x] Consignment stock: staff record stock the seller brings ("Stock arrived") and unsold stock handed back ("Returned to seller"); everything stays in the append-only stock history the seller can read.
- [x] Statement (append-only): a sale is added when the order is completed and paid (item value minus commission, rounded down in the seller's favour); returns after that reverse it; cancelled orders add nothing; refunds without a returned item do not touch the seller unless finance staff record an adjustment with a reason. Payouts are recorded by finance staff after paying (cash or bank transfer with reference), never above what is owed, with a PO-number; nothing is paid automatically (tested).
- [x] Seller Hub at `/seller`: authenticator app required, English/Arabic, dashboard, products, sales without customer details, statement with CSV, stock history, shop profile. Each screen shows only the signed-in seller's shop (tested); sellers can't open the admin panel.
- [x] Admin: Marketplace → Seller applications and Sellers; listing review on Products; "Needs your attention" shows applications, listings to review, sellers waiting for a payout and paused or closed shops still holding stock.
- [x] Pausing hides a shop's products at once; closing archives them. Deleting the seller's account closes the shop and removes contact details, bio and logo but keeps the statement.
- [x] App: "Sell on RIVALO" (apply, status, withdraw, reason when not approved, Seller Hub address for approved sellers), shop pages, "Sold by". English, Arabic and Kurdish; seller messages in the inbox.
- [x] `rivalo:launch-check` covers the marketplace (store on, seller terms published without placeholders, sample sellers).
- [ ] **Owner and legal decisions before switching it on (D23, D24):** commission per seller, payout schedule, return period, who bears refunds without a return, seller requirements, and a lawyer's review of the "Selling on RIVALO" terms (draft with [BRACKETED] items).
- [ ] Not built: several staff accounts per shop, sellers shipping orders themselves, card payments, automatic payouts.

### M8
- [x] Catalog: products with English/Arabic/Kurdish names and descriptions, categories, sport, photos (re-encoded on upload), featured products, sizes or options with their own SKU, optional price and stock. Draft/active/archived; ordered products are archived, not deleted.
- [x] Stock: every change is an append-only movement (restock, count correction with reason, order, cancellation, return). The size's stock equals the ledger (tested). The app shows "in stock", "only N left" (3 or fewer) or "sold out", never exact counts.
- [x] Bag on the server: today's prices, per-order limits, flags items that sold out or dropped below the quantity, blocks checkout until fixed.
- [x] Checkout: pickup point or delivery area (delivery only to areas staff configure, each with its own fee). The app sends the total the customer saw; if prices or fees changed, the order is refused, never charged differently. Stock is reserved at checkout under row locks, so the last item can't be sold twice (tested). Verified email required; at most 3 orders in progress per customer.
- [x] Payment: **cash on pickup or delivery only**, recorded by staff at handover with a receipt number (SR-…). No card payment, no stored card data. Refunds record cash actually handed back; returned items can go back into stock.
- [x] Orders: placed → confirmed → ready for pickup / out for delivery → completed, or cancelled (customer while placed/confirmed; staff any time before completion with a reason shown to the customer; a paid order needs its refund recorded first). Names and prices are copied into the order. Customers get inbox messages (placed, ready, on its way, completed, cancelled) under the new "Store orders" notification setting.
- [x] Privacy: orders and delivery details are visible only to the customer and store staff; another customer's order number returns "not found".
- [x] Admin (Store group, store managers): Products, Orders, Stock history, Pickup points, Delivery areas, Sales report with CSV; "Needs your attention" shows new orders, orders not collected after a week and low stock.
- [x] App: Store tab (only while `store_enabled` is on; otherwise News, as before), product page, bag, checkout, My orders, order page with progress. English, Arabic and Kurdish.
- [x] `rivalo:launch-check` covers the store (pickup or delivery available, something in stock, no sample products).
- [ ] **Owner decisions before switching the store on:** real products, prices, stock and photos; pickup points and opening hours; whether to deliver and the fee per area (D19, D20).
- [ ] Card payments: only after a payment provider is chosen and verified (not built).

### M7
- [x] Reports: players report a player (offensive name or photo, harassment, cheating, impersonation, spam, other), a team, or a problem with the app / feedback (with app version and platform). One open report per reporter and subject; rate limited. The reported player never learns who reported them.
- [x] Moderation queue (Admin → Community → Reports, open count in the sidebar): snapshot of what was reported, reporter's history, other reports about the same subject. Staff dismiss, remove a photo or logo, remove a display name, rename a team, warn (inbox message, optional custom text) or suspend (ends every app session; staff accounts only by a super admin). Every decision needs a reason and is audited; the same decision closes other open reports about that subject. Nobody handles a report that involves them.
- [x] The affected player is told what was removed, with a link to the Community guidelines. Reporters are told their report was reviewed, never what happened to the other player. Safety messages go to the inbox only (no phone notification).
- [x] Blocking: private; hides both players from each other's search; withdraws pending team invitations between them and refuses new ones. Blocked players list with unblock in Settings.
- [x] Policy pages: Terms, Privacy, Community guidelines with English/Arabic/Kurdish text, numbered append-only versions with change notes, readable before sign-up. Terms and Privacy show "Draft: pending legal review" until a super admin records a qualified reviewer's approval **for that exact version**; publishing a new version makes it pending again. Seeded Terms/Privacy are structured drafts with [BRACKETED] placeholders; the Privacy draft describes what the software really stores. Untranslated pages show the English text with a note.
- [x] Admin polish: "Needs your attention" dashboard (only items the staff member can act on), report counts in the sidebar, policy version history.
- [x] Translations: every app text now exists in Arabic and Kurdish (246 Arabic and 134 Kurdish texts were missing and showed in English); every admin/server text has Arabic; every server message the app can show has Kurdish (117 added), plus Kurdish validation, sign-in and password-reset messages. Tests fail if a text or placeholder goes missing. Review sheet for native speakers: docs/translation-review.
- [x] Right-to-left review: layout uses directional padding and auto-mirroring chevrons; two arrow icons that did not mirror were fixed. English policy text inside the Arabic app keeps left-to-right layout.
- [x] Security review (docs/08-SECURITY.md): team logos re-encoded (GPS metadata), sign-in timing equalized, deletion scrubs inbox/blocks/report snapshots, browser security headers, `no-store` on signed-in API responses, idle app sessions signed out after 180 days, structural tests for route protection and admin policies, `composer audit` clean.
- [x] `php artisan rivalo:launch-check`: read-only PASS/WARN/FAIL check of configuration, operations (scheduler heartbeat, queue), data (no sample data, MFA) and policies.
- [x] Pilot runbook (docs/09-PILOT-RUNBOOK.md) and an updated manual checklist (docs/07).
- [ ] **Native-speaker review** of Arabic and Kurdish (owner: find reviewers; sheet ready).
- [ ] **Legal review** of Terms and Privacy, and decision D6 (owner and lawyer).
- [ ] **The pilot event itself** (owner and staff, following the runbook).
- [ ] Independent penetration test of staging before public launch.

### M6
- [x] In-app inbox for every signed-in user: team invitations, waitlist promotion, draw published, new tournaments in followed disciplines, check-in and match reminders, match time changes, approved and corrected results, dispute decisions, new badges and announcements. Each message links to what it is about. Unread count on a bell on Home; mark one or all as read.
- [x] Text is written in each user's language when sent (English, Arabic, Kurmanji for notification texts).
- [x] Messages are sent only after the action commits (a rolled-back action notifies nobody), a delivery failure never breaks the action, and dedupe keys mean each event reaches a user once (reminders run every 5 minutes and still arrive once).
- [x] Phone notifications follow each user's notification preferences per category; the inbox always receives the message. Push runs on the queue. Providers: `log` (default, nothing leaves the server) and `fcm` (Firebase HTTP v1, server-side credentials only). Tokens the provider rejects are deleted. Device tokens are tied to a device's sign-in and removed on sign-out.
- [x] Badges: 7 default badges (first match, on site, first win, 10 wins, regular, podium, champion) earned only from approved results, final placements and check-ins. Re-checked when the record changes; withdrawn automatically if a correction means they no longer apply. Staff can award (manual) or remove badges with a reason; a staff removal is never undone automatically. Badges and points on public profiles follow the tournament-history privacy setting.
- [x] Announcements: staff write a title and message, choose everyone / followers of a discipline / a tournament's entrants, optional language, category (which preference controls the phone notification) and optional link; send now (with recipient count) or schedule. Sent announcements can't be edited.
- [x] Admin: Community → Badges and Announcements, in English and Arabic.
- [x] App: inbox screen, bell with unread badge, badges grid on Profile (earned in gold, locked with progress), badges page, badges and points on public profiles.
- [ ] **Not active yet: phone delivery.** It needs a RIVALO Firebase project (Android `google-services.json`, iOS `GoogleService-Info.plist` and an APNs key), then `firebase_messaging` in the app and `PUSH_DRIVER=fcm` on the server. See docs/07.
- [ ] Production needs a running queue worker and the scheduler (cron), or announcements, push and reminders will wait in the queue.

### M5
- [x] Draw from eligible entrants (reserved, checked in, fee settled). Seeded entries first; the rest drawn at random with a recorded draw number, so any draw can be reproduced. Redraw allowed until the first result exists. Draft fixtures are staff-only until published.
- [x] Single elimination: standard seeding order, byes to top seeds that advance automatically, round labels, optional third-place match (tournament third-place method "playoff match", ≥ 4 entrants).
- [x] Round robin (circle method, every pair once). Groups → knockout: snake-seeded groups, then a knockout built from the group tables with group winners seeded first (same-group teams kept apart).
- [x] Results: players in the match, assigned organizers and referees submit (score, sets, or walkover/forfeit/no-show; staff only: cancellation, disqualification). A new submission supersedes an unreviewed one. Only an assigned referee (or global manager) approves or rejects. Level knockout scores need a tiebreak winner; league/group matches can be drawn.
- [x] Approval completes the match and moves the winner (and, for semi-finals, the loser to the third-place match) on automatically.
- [x] Corrections by a referee need a reason: the old version is kept as superseded, its points are reversed, the bracket updates, and pending submissions in later matches are voided. Refused once a dependent later match has been played or placements are final.
- [x] Disputes: players in the match, within the dispute window (draft: 48 h, `RIVALO_DISPUTE_WINDOW_HOURS`), one open dispute per result. Referees reject or uphold with the corrected result.
- [x] Points tables (D3): drafts award nothing. Publishing needs a note recording the owner's decision; published tables are frozen (new version instead) and replace the previous table for the discipline/season. A tournament uses a published table only if it is ranking-eligible; the table locks after the first award.
- [x] Append-only points ledger with a unique idempotency key per award (retries never double-award) and reversal rows for corrections. Every active roster member receives the entry's points.
- [x] Final placements (bracket final/third place, shared third, or league table) award placement and participation points and complete the tournament.
- [x] Leaderboards per discipline, per season or all time; ties share a rank; only active users. Public API exposes names and usernames only.
- [x] Admin: Competition → Fixtures and results (draw, publish, schedule, enter/review/correct results, disputes, knockout from groups, finalize) and Points tables, in English and Arabic.
- [x] App: fixtures on the tournament page (bracket with connector lines, league/group tables, matches by round, final standings), match screen (score, submit result with steppers or sets, dispute), real leaderboards with podium and ties, points history, points total on profile.
- [ ] Not built yet: result evidence uploads (photos), team leaderboards, head-to-head tiebreakers, leaderboard snapshots. Each needs an owner decision or is planned for later.

### M4B
- [x] Sponsors (admin only; contact details private). A sponsor appears in the app only with display consent (`is_public`) and an active contribution; sponsorship amounts are never sent to the app.
- [x] Contributions: cash (unrestricted / prizes only / expenses only), in kind, or venue. Cash received is recorded as it arrives (partial receipts allowed, never more than pledged) and lands in the matching fund.
- [x] Prize-only money can never pay the organizer or expenses; expenses-only money can only pay expenses; only unrestricted money can be set aside. No manual entry can take a fund below zero (service checks, tested).
- [x] Finance rules per tournament (prize pool share and organizer share of net entry revenue) start as **drafts** (D4 open). Drafts only produce labelled projections. Organizer payouts and pool-share prize amounts are refused until a finance manager approves the rules with a note.
- [x] After registration opens, rule and prize changes need a written reason and are audited as corrections; any rule change returns the rules to draft. Rules cannot change once formula-based payouts exist.
- [x] Prize labels: **Estimated** always allowed; **Finalized** for fixed amounts, or pool shares only with approved rules and closed registration; **Guaranteed** only for fixed cash backed by sponsor cash already received (and that receipt can't then be reversed).
- [x] Append-only tournament ledger (`LG-{tournament}-{seq}`): entry fees, refunds and voided receipts flow in automatically from the check-in desk in the same transaction; expenses, set-asides, organizer and prize payouts record cash actually handed over (payee required). Corrections are reversal entries; rows can't be edited or deleted.
- [x] Prize payouts need a completed tournament, a finalized or guaranteed prize, and can't exceed the prize amount.
- [x] Admin: Finance → Tournament finance (per tournament), Sponsors, Finance report (all tournaments, totals per currency, CSV download, export audited). English and Arabic. Finance managers only.
- [x] App: tournament page shows prize tiers (podium colours, amount or "To be announced", item, Estimated/Finalized/Guaranteed label) and consenting sponsors.

### M4A
- [x] Each roster member gets a signed check-in QR code in the app (works offline once loaded). The QR links to the admin check-in desk, so any phone camera can scan it. Only assigned organizers with MFA can open it.
- [x] A player cannot mark their own entry fee as paid (no API exists; the service refuses non-staff).
- [x] Staff record the exact fee in cash and get a receipt number (RV-…). A second payment for the same entry is refused (service and database index).
- [x] Mistakes are voided with a reason and stay in history. Refunds (RF-…) cannot exceed what was received. Payment rows cannot be edited or deleted.
- [x] Check-in requires a reserved place and a settled fee. Check-in then makes the entry eligible. Late check-in needs a reason. Voiding a payment removes eligibility.
- [x] New payments stop when the paid-entry flag is off; refunds still work.

### M3
- [x] A player can't register twice for the same tournament, or appear on two rosters in one tournament (enforced by the service **and** a database index).
- [x] Registrations over `max_entrants` go to the waitlist. Cancelling promotes the next waitlisted entry.
- [x] Only accepted, active team members can be added to a roster. Roster size respects the tournament's limits.
- [x] Later team changes do not alter a submitted roster snapshot.
- [x] A paid-entry tournament can't be published or registered for while `paid_entry_participation` is disabled. Free tournaments work.
- [x] Organizers can only manage tournaments they're assigned to.
