# Screen map

Legend: ✅ built in this repository · 🟡 placeholder, clearly labeled "Coming in a future update" · ⬜ planned

## Mobile (Flutter)

```
Launch
├── Splash / session restore ✅
├── Welcome (wordmark, Sign in / Create account / Browse as guest) ✅
├── Sign in ✅ ─ Forgot password ✅
├── Create account ✅ → Verify email notice ✅ (resend, "I've verified")
└── Onboarding: choose sports & games ✅

Tab shell (frosted tab bar, each tab keeps its own stack)
├── Home ✅
│   ├── Upcoming tournaments for my disciplines ✅
│   ├── My active registrations ✅
│   └── Pending team invitations ✅
├── Compete ✅
│   ├── Tournament list (filter by sport; upcoming) ✅
│   ├── Tournament detail (rules, schedule, venue, prize text, status chips) ✅
│   │   ├── Register: individual / pair / team, roster picker ✅
│   │   ├── My entry: registration / payment / check-in / eligibility status, receipts ✅
│   │   ├── Check-in pass: QR code (works offline once loaded) ✅
│   │   ├── Fixtures ✅: bracket (connector lines, third-place match), league/group tables, matches by round, final standings
│   │   ├── Match ✅: scoreboard, status, time and place; submit result (steppers or sets; walkover/forfeit/no-show); dispute within the window
│   │   ├── Prizes: podium cards with Estimated / Finalized / Guaranteed labels ✅
│   │   └── Sponsor strip (consenting sponsors only, no amounts) ✅
│   └── My teams ✅
│       ├── Create team ✅
│       ├── Team detail: members, roles, pending invites, leave/disband ✅ (full membership history: admin)
│       ├── Invite player (by username) ✅
│       ├── Invitations inbox (accept / decline) ✅
│       └── Captain transfer (nominate / accept) ✅
├── Rankings ✅ discipline chips, this season / all time, podium with real leaders (ties share a rank), full list, "You" highlighted; empty podium with an honest message when no points exist
├── News ✅ (stands in for Store until store_enabled)
│   ├── Featured photo slider (auto-advance, swipe, page dots, parallax) ✅
│   ├── Category filter: All, Announcement, Tournament, Results, Community, Guide ✅
│   ├── Feed: lead story + rows with thumbnails ✅
│   └── Article: stretchy photo header, reading time, linked tournament ✅
└── Profile ✅
    ├── My profile (public preview) ✅
    ├── Edit profile (photo, display name, city) ✅
    ├── Sports & games ✅
    ├── Settings (grouped inset list) ✅
    │   ├── Privacy ✅
    │   ├── Notifications ✅ (preferences stored; delivery in M6)
    │   ├── Language ✅
    │   ├── Sessions: sign out this device / all devices ✅
    │   ├── Safety: Community guidelines, Blocked players (unblock), Report a problem or send feedback ✅
    │   ├── Support: Terms of use, Privacy policy, contact address ✅
    │   └── Delete account (request + grace period + cancel) ✅
    ├── Public profile of another player ✅
    ├── Points history ✅ (every award with its reason; corrections as negative rows)
    ├── Badges ✅ (grid on Profile: earned in gold, locked with progress; full badges page; badges on public profiles)
    └── Report and block ✅ ("More" on another player's profile: Report, Block/Unblock; on a team page you're not in: Report team; guests are asked to create an account)
Policy pages ✅ (/legal/terms, /legal/privacy, /legal/community-guidelines: readable signed out, as a guest and signed in; linked from the sign-up form; "Draft: pending legal review" and "Not translated yet" shown plainly)
Inbox ✅ (bell on Home with unread count; messages link to tournament, match, team, invitations, news, badges, a policy page, an order or Sell on RIVALO; report outcomes, moderation notices and seller messages; mark all read)
Store ✅ (fourth tab while `store_enabled` is on; News then opens from Home)
├── Store home: featured rail, category chips (Jerseys, Apparel, Accessories, Equipment), product grid; photo or drawn illustration (never a fake photo)
├── Product: photos, price, size chips (sold-out sizes struck through, "Only 2 left"), quantity, Add to bag (guests: create-account sheet)
├── Bag: today's prices, quantity steppers, reduced-stock and unavailable warnings, Checkout disabled until fixed
├── Checkout (full screen): pickup point or delivery area (fee and estimate), recipient details, summary, "Pay in cash on pickup/delivery", Place order (sends the total the customer saw)
├── My orders (also from Profile): list; order page with progress timeline, pickup/delivery details, items (with "Sold by" for seller items), totals, payment and receipt, Cancel while placed or confirmed
├── "Sold by" (M9): product cards, product page row ("Sold by …, Approved seller", opens the shop; official products say "Official RIVALO product"), bag lines and order lines
├── Shops rail and shop page (M9, while `marketplace_enabled` is on): logo or initials, name, approved-seller badge, description, products, what RIVALO does for seller products
└── "Sell on RIVALO" card (M9) → Sell on RIVALO
Sell on RIVALO ✅ (Profile and Store, while `marketplace_enabled` is on; guests get the create-account sheet)
├── How it works (3 steps) and "Selling on RIVALO: terms" (draft policy page)
├── Apply (full screen): business name, phone (staff only), city, categories, description, link; field errors from the server
├── Under review: what happens next, Withdraw application
├── Not approved: the reason staff gave, Apply again
└── Approved: shop name and status (paused/closed with reason), Seller Hub address with Copy, "See your shop in the store"
Developer only: Design-system gallery ✅ (debug builds)
```

**Guest mode** ✅: "Browse as guest" opens Home, Compete (tournament list and detail), Rankings, News, team pages and public player profiles without an account. Guests make no authenticated API calls. Register, create or join a team, invitations and settings open a "Create a free account" sheet (Create account / Sign in / Not now). The Profile tab shows what an account unlocks, a local language choice, and "Back to welcome screen". Guest mode is in memory only and isn't remembered after the app restarts.

**Languages** ✅: the app offers English, العربية (right-to-left) and Kurdî (Kurmancî). A phone set to Kurdish opens in Kurmanji. Dates in Kurmanji use a numeric format (17.10.2026 · 20:00). The admin panel offers English and العربية (right-to-left) from the user menu or the sign-in page; each staff member's choice is saved to their account.

**Visual design** ✅: Home leads with the featured news slider, then your next tournament (cover, countdown, status), round sport shortcuts, a swipeable rail of tournament cards and the latest news. Tournament cards and pages show a cover photo, or generated sport artwork when there is none (pitch, padel court, pool table or e-sports arena drawn in perspective; it is an illustration, never a fake photo), plus a status chip, a countdown ("Starts in 3 days", "Closes in 5 h"), and a bar showing places taken. Cards shrink slightly when pressed, content fades in as it loads, and the slider auto-advances. All motion turns off when the phone's "reduce motion" setting is on.

States on every data screen: **loading** (skeleton), **empty** (purposeful message plus action), **error** (message plus retry), **offline** (banner, plus cached data where available).

## Admin panel (Filament, `/admin`)

| Module | Status | Who |
|---|---|---|
| Overview dashboard (real counts, sample data labeled) | ✅ | all staff |
| Users (view, suspend, roles, MFA reset, sample flag) | ✅ | super admin (support staff: view only) |
| Roles and permissions | ✅ role assignment on users; role→permission mapping defined in `app/Enums/RoleName.php` | super admin |
| Sports catalog: sports and disciplines | ✅ | super admin |
| Seasons | ✅ | super admin |
| Teams (view, membership history, force captain transfer, disband) | ✅ | support, super admin |
| Tournaments (CRUD, staff assignment, status transitions) | ✅ | organizer (assigned only), super admin |
| Registrations (per tournament, statuses) | ✅ view + cancel/no-show | organizer (assigned) |
| Feature flags (compliance) | ✅ | super admin |
| Audit logs | ✅ read-only | super admin |
| Venue check-in desk (scan QR link, type code or search; record cash payment, void, refund, check in, eligibility) | ✅ | organizer (assigned), super admin |
| Finance → Tournament finance: draft/approved rules, projections, funds, prizes, sponsor contributions, append-only money records, expenses, set-asides, payouts, reversals | ✅ | finance manager (super admin) |
| Finance → Sponsors (private contacts, display consent) | ✅ | finance manager |
| Finance → Finance report (all tournaments, totals per currency, CSV) | ✅ | finance manager |
| Competition → Fixtures and results (draw, publish, schedule, enter/review/correct results, disputes, knockout from groups, final placements) | ✅ | organizer (assigned) for fixtures; referee (assigned) for approvals |
| Competition → Points tables (draft, publish with note, retire, new version) | ✅ | points manager (super admin) |
| News (articles, cover photo, featured slider, language targeting, scheduling, archive) | ✅ | content editor, super admin |
| Community → Badges (define, award or remove with a reason) | ✅ | content editor, super admin |
| Community → Announcements (audience, language, category, link; send now or schedule) | ✅ | content editor, super admin |
| Dashboard → Needs your attention (open reports, results waiting for approval, open disputes, unpublished or unreviewed policies, scheduler and failed jobs; only what you can act on) | ✅ | all staff |
| Community → Reports (queue with open count in the sidebar; snapshot of what was reported; reporter history; take action or dismiss with an audited reason) | ✅ | support staff, super admin |
| Community → Policy pages (English/Arabic/Kurdish drafts, publish numbered versions, version history, record legal review) | ✅ | content editor; legal review: super admin |
| Store → Products (three-language name/description, price, category, status, featured; Sizes and stock with "Stock arrived" and "Count stock"; Photos) | ✅ | store manager, super admin |
| Store → Orders (new-order count in the sidebar; confirm, ready for pickup / out for delivery, handed over and paid, cash payment, refund or return, cancel with reason) | ✅ | store manager |
| Store → Stock history (read-only) · Pickup points · Delivery areas · Sales report (cash received, refunds, items sold, low stock, CSV) | ✅ | store manager |
| Marketplace → Seller applications (pending count in the sidebar; approve with shop name, commission from the signed agreement, agreement reference, what was checked and a confirmation; reject with a reason shown to the applicant) | ✅ | store manager (sellers.manage), super admin |
| Marketplace → Sellers (balance owed, statement, products; edit public profile and contacts; pause, reopen, close; change commission after a new agreement; record payout or adjustment: finance staff only; statement CSV) | ✅ | store manager; payouts and adjustments: finance (super admin) |
| Store → Products for seller listings ("Sold by" column and filter; Approve listing, Request changes or Take off sale with a note; "Returned to seller" on sizes) | ✅ | store manager |

**Seller Hub** (`/seller`, M9): separate web panel for approved sellers, English/Arabic, authenticator app required, only their own shop.

| Screen | Status |
|---|---|
| Your shop: what RIVALO owes, earned this month, items in orders in progress, products by status, low stock, how selling works | ✅ |
| Your products: add (always a draft), edit while draft or sent back, sizes and photos, Submit for review, take off sale to edit, archive; stock shown read-only | ✅ |
| Sales: their order lines (no customer details), item value, their share, status, whether it is on the statement | ✅ |
| Statement: sales, returns, adjustments, payouts with running summary; CSV | ✅ |
| Stock history (read-only) · Shop profile (logo, description, private contacts; name and commission set by RIVALO) | ✅ |
