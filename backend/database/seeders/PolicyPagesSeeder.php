<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Production-safe: creates the three policy pages as UNPUBLISHED drafts if they don't exist.
 * Never overwrites staff edits. Nothing here is legal advice: Terms and Privacy are structured
 * drafts with [BRACKETED] placeholders that a qualified reviewer must complete (decision D6).
 * The privacy draft describes what the software actually stores, so the reviewer starts from facts.
 */
class PolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            Page::firstOrCreate(['slug' => $slug], $page);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pages(): array
    {
        return [
            Page::GUIDELINES => [
                'requires_legal_review' => false,
                'title_translations' => [
                    'en' => 'Community guidelines',
                    'ar' => 'إرشادات المجتمع',
                    'kmr' => 'Rêbernameya civakê',
                ],
                'body_translations' => [
                    'en' => <<<'TXT'
RIVALO is for people who love to compete. These guidelines keep it fair and friendly for everyone.

Play fair
Enter real scores only. Don't fix matches, use someone else's account, or play under another player's name. Results are checked by referees and can be disputed for a limited time after they are approved.

Respect other players
No harassment, threats, insults or hate, on or off the field. That includes names, team names and photos.

Be yourself
Don't pretend to be another player, a team, a sponsor or RIVALO staff.

No spam, scams or betting
Don't advertise, ask players for money, or organise bets on matches. RIVALO has no betting and no cash prizes for staking.

Keep it suitable for everyone
Profile photos, team logos and names must be suitable for a public sports app.

Report and block
If something isn't right, open the player's profile or the team page, tap the menu and choose Report. Reports are private: the other player is never told who reported them. You can also block a player. Blocking hides you from each other in search and stops team invitations between you.

What our team can do
Staff review every report. Depending on what happened, they may remove a photo or a name, rename a team, send a warning, or suspend an account. Staff record a reason for every decision.

Questions or appeals
If you think a decision about your account was wrong, contact support from Settings and tell us why.
TXT,
                    'ar' => <<<'TXT'
ريفالو مكان لمن يحب المنافسة. هذه الإرشادات تجعلها عادلة وودّية للجميع.

العب بنزاهة
أدخل النتائج الحقيقية فقط. لا تتلاعب بالمباريات، ولا تستخدم حساب شخص آخر، ولا تلعب باسم لاعب آخر. يراجع الحكام النتائج، ويمكن الاعتراض عليها خلال مدة محددة بعد اعتمادها.

احترم اللاعبين الآخرين
لا تحرّش ولا تهديد ولا إهانات ولا كراهية، داخل الملعب أو خارجه. ويشمل ذلك الأسماء وأسماء الفرق والصور.

كن نفسك
لا تنتحل صفة لاعب آخر أو فريق أو راعٍ أو أحد موظفي ريفالو.

لا رسائل مزعجة ولا احتيال ولا مراهنات
لا تعلن، ولا تطلب المال من اللاعبين، ولا تنظّم رهانات على المباريات. لا توجد في ريفالو مراهنات ولا جوائز نقدية مقابل رهان.

محتوى مناسب للجميع
يجب أن تكون صور الملف الشخصي وشعارات الفرق والأسماء مناسبة لتطبيق رياضي عام.

الإبلاغ والحظر
إذا رأيت شيئًا غير مناسب، افتح ملف اللاعب أو صفحة الفريق، واضغط على القائمة واختر «إبلاغ». البلاغات سرية: لا نخبر اللاعب الآخر أبدًا بمن أبلغ عنه. ويمكنك أيضًا حظر لاعب. الحظر يخفيكما عن بعضكما في البحث ويمنع دعوات الفرق بينكما.

ما الذي يمكن لفريقنا فعله
يراجع الموظفون كل بلاغ. وبحسب ما حدث، قد يزيلون صورة أو اسمًا، أو يغيّرون اسم فريق، أو يرسلون تحذيرًا، أو يعلّقون حسابًا. ويسجّل الموظفون سببًا لكل قرار.

أسئلة أو اعتراضات
إذا كنت تعتقد أن قرارًا بشأن حسابك كان خاطئًا، تواصل مع الدعم من الإعدادات وأخبرنا بالسبب.
TXT,
                    'kmr' => <<<'TXT'
RIVALO ji bo kesên ku hez ji pêşbaziyê dikin e. Ev rêbername wê ji bo hemûyan dadperwer û dostane dihêle.

Bi dadperwerî bilîze
Tenê encamên rast binivîse. Lîstikan saz neke, hesabê kesekî din bi kar neyîne û bi navê lîstikvanekî din nelîze. Hakem encaman kontrol dikin û piştî pejirandinê ji bo demeke diyar dikare îtîraz li wan were kirin.

Rêzê li lîstikvanên din bigire
Tacîz, gef, heqaret û nefret tune, li qadê û derveyî qadê jî. Ev nav, navên tîman û wêneyan jî digire nav xwe.

Xwe be
Xwe wekî lîstikvanekî din, tîmekê, sponsorekî an karmendekî RIVALO nîşan nede.

Spam, xapandin û bahîs tune
Reklamê neke, pere ji lîstikvanan nexwaze û li ser lîstikan bahîsan saz neke. Di RIVALO de bahîs û xelatên pereyî yên bi bahîsê tune ne.

Ji bo herkesî guncav
Wêneyên profîlê, logoyên tîman û nav divê ji bo sepana werzişê ya giştî guncav bin.

Rapor bike û asteng bike
Heke tiştek ne di cih de be, profîla lîstikvan an rûpela tîmê veke, menûyê bitikîne û Rapor hilbijêre. Rapor veşartî ne: em qet ji lîstikvanê din re nabêjin kê rapor kiriye. Tu dikarî lîstikvanekî asteng jî bikî. Astengkirin we di lêgerînê de ji hev vedişêre û vexwendinên tîmê di navbera we de radiwestîne.

Tîma me dikare çi bike
Karmend her raporê dinirxînin. Li gorî tiştê qewimî, dibe ku ew wêneyek an navek rakin, navê tîmekê biguherînin, hişyariyekê bişînin an hesabekî rawestînin. Karmend ji bo her biryarekê sedemekê tomar dikin.

Pirs an îtîraz
Heke tu difikirî ku biryarek li ser hesabê te şaş bû, ji Mîhengan bi piştgiriyê re têkilî dayne û sedemê ji me re bibêje.
TXT,
                ],
            ],

            Page::TERMS => [
                'requires_legal_review' => true,
                'title_translations' => ['en' => 'Terms of use'],
                'body_translations' => ['en' => <<<'TXT'
DRAFT — PENDING LEGAL REVIEW. This text has not been approved by a lawyer. Every [BRACKETED] item must be completed by a qualified reviewer before launch (decision D6).

1. Who we are
RIVALO is operated by [LEGAL NAME OF THE OPERATING COMPANY], [REGISTERED ADDRESS]. Contact: the support address shown in the app under Settings.

2. Who can use RIVALO
[MINIMUM AGE AND ELIGIBILITY — not yet decided; see decision D6. Do not publish until this is completed.]

3. Your account
Keep your password private. You are responsible for activity on your account. You can sign out of every device and delete your account at any time from Settings.

4. Tournaments
Each tournament page shows its rules, schedule, capacity and any prizes. Organisers and referees run tournaments and approve results. Results can be disputed for a limited time after approval, as shown in the app. Points and badges come only from approved results, final placements and check-ins.

5. Entry fees and prizes
[ENTRY FEES — paid-entry tournaments are switched off until decision D7 is made. Complete this section only after that decision.]
Prize amounts are labelled Estimated, Finalized or Guaranteed. Only Guaranteed amounts are promised. RIVALO has no betting and no withdrawable wallet.

6. Your conduct
You agree to follow the Community guidelines. Staff may remove content, warn you or suspend your account if you don't.

7. Your content
You keep ownership of your photos and names. You allow RIVALO to show them in the app as your privacy settings allow.

8. Changes to these terms
Each published version has a version number and date. [HOW USERS ARE TOLD ABOUT CHANGES, AND WHETHER RE-ACCEPTANCE IS NEEDED.]

9. Liability
[LIMITATION OF LIABILITY — to be written by the legal reviewer for the applicable jurisdiction.]

10. Governing law
[GOVERNING LAW AND PLACE OF DISPUTES.]
TXT],
            ],

            Page::SELLER_TERMS => [
                'requires_legal_review' => true,
                'title_translations' => ['en' => 'Selling on RIVALO'],
                'body_translations' => ['en' => <<<'TXT'
DRAFT — PENDING LEGAL REVIEW. This text describes how the RIVALO software handles sellers today. It has not been approved by a lawyer. Every [BRACKETED] item must be completed by a qualified reviewer before sellers are accepted (decisions D6 and D22).

1. Who can sell
Only sellers approved by RIVALO. Staff review each application and check who the seller is before approving it. [BUSINESS REGISTRATION, TAX AND OTHER REQUIREMENTS FOR SELLERS.]

2. The agreement
Every seller signs a written agreement with RIVALO before selling. It states the commission RIVALO keeps from each sale. [WHO SIGNS FOR RIVALO; LENGTH OF THE AGREEMENT; NOTICE PERIOD.]

3. Products
Sellers describe their products honestly, use only photos they own, and set prices in the store currency. RIVALO staff review every product before it goes on sale and can take any product off sale. [PRODUCTS THAT MAY NOT BE SOLD.]

4. Stock, pickup and delivery
Sellers bring their stock to RIVALO. RIVALO keeps it, sells it in the app and hands it to customers at a pickup point or by delivery. Unsold stock is handed back to the seller when they ask. [RESPONSIBILITY FOR LOSS OR DAMAGE WHILE RIVALO HOLDS THE STOCK.]

5. Payment and commission
Customers pay RIVALO in cash when they collect or receive their order. When an order is completed, the item price minus the agreed commission is added to the seller's statement in the Seller Hub. Items returned later are taken off again. RIVALO pays sellers in cash or by bank transfer and records each payout on the statement. Nothing is paid automatically. [PAYOUT SCHEDULE; WHO BEARS A REFUND WHEN NO ITEM IS RETURNED; TAXES AND INVOICES.]

6. Returns and complaints
Customers contact RIVALO, and RIVALO tells the seller. [RETURN PERIOD AND CONDITIONS.]

7. Customer data
Sellers do not receive customers' names, phone numbers or addresses.

8. Pausing and ending
RIVALO can pause a shop at once, with a reason, while it looks into a problem. [HOW EITHER SIDE ENDS THE AGREEMENT.] When a shop closes, the balance is settled and unsold stock is handed back.

9. Liability and governing law
[TO BE WRITTEN BY THE LEGAL REVIEWER FOR THE APPLICABLE JURISDICTION.]
TXT],
            ],

            Page::PRIVACY => [
                'requires_legal_review' => true,
                'title_translations' => ['en' => 'Privacy policy'],
                'body_translations' => ['en' => <<<'TXT'
DRAFT — PENDING LEGAL REVIEW. This text describes what the RIVALO software stores today. It has not been approved by a lawyer. Every [BRACKETED] item must be completed before launch (decision D6).

Who is responsible for your data
[LEGAL NAME, ADDRESS AND CONTACT OF THE DATA CONTROLLER.]

What we store
• Account: username, email address, password (stored only as a secure hash), language, and the sports you follow.
• Profile (optional): display name, city, profile photo. Photos are re-encoded when uploaded, which removes hidden data such as GPS location.
• Phone number (optional): private. It is never shown to other players.
• Competition: teams, tournament registrations, check-ins, match results, points, badges and disputes.
• Venue payments: if staff record a cash payment at a venue, we store the amount and status. We do not store card details.
• Store orders (if you shop): the items, prices, pickup point or delivery area, and for delivery the recipient's name, phone number and address. Delivery details are visible only to you and store staff, and are removed from finished orders when you delete your account.
• Selling (if you apply): your business name, phone number, city and what you sell. For approved sellers: the shop profile, the signed agreement's reference and commission, what staff checked, and the seller statement.
• Devices: each signed-in device (name and when it was last used) and, if you allow notifications, a push token for that device.
• Messages: your notification inbox.
• Safety: reports you send and reports about you, blocks you set, and staff decisions with their reasons.
• Security logs: important actions (for example creating a team, changing settings, and every staff action) are logged with the time, IP address and app or browser details. Servers keep technical logs for [RETENTION PERIOD].

What other players can see
Your username always. Your display name, city, teams, tournament history, points and badges only as your Privacy settings allow. Tournament entry lists and results are public.

What we don't do
We don't sell your data. The app contains no advertising and no third-party analytics.

Who processes data for us
Hosting: [HOSTING PROVIDER AND COUNTRY]. Email delivery: [EMAIL PROVIDER]. Phone notifications: Google Firebase Cloud Messaging and Apple Push Notification service, if notifications are switched on. [ANY OTHER PROCESSORS.]

How long we keep it
You can delete your account in Settings. After a 14-day grace period it is anonymised: your name, email, phone, photo, city, inbox, blocks and devices are removed. Tournament results and money records stay in anonymised form so past tournaments remain correct. [LEGAL RETENTION PERIODS FOR FINANCIAL RECORDS.]

Your rights
[RIGHTS UNDER THE APPLICABLE LAW, AND HOW TO EXERCISE THEM.] Contact support from Settings.

Children
[POLICY ON MINORS — depends on decision D6. Not yet decided.]

Changes
Each published version has a version number and date shown at the top of this page.
TXT],
            ],
        ];
    }
}
