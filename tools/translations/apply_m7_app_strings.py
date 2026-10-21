"""
Milestone 7: adds the new safety/policy strings to the app (en, ar, kmr) and fills every key
that had no Arabic or Kurmanji text yet. Run from the repository root:

    py tools/translations/apply_m7_app_strings.py

ARB files are rewritten in the English key order. Arabic and Kurmanji remain drafts pending
native-speaker review (see docs/translation-review).
"""
import collections
import json
import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).parent))
from fill_ar_m7 import AR  # noqa: E402
from fill_kmr_m7 import KMR  # noqa: E402

L10N = pathlib.Path(__file__).resolve().parents[2] / 'mobile' / 'lib' / 'core' / 'l10n'

S = {'type': 'String'}

# key: (English, Arabic, Kurmanji, placeholders or None)
NEW = {
    'registerLegalNotice': (
        'Before you sign up, you can read our Terms of use, Privacy policy and Community guidelines. Terms and Privacy are drafts until their legal review is complete.',
        'قبل التسجيل، يمكنك قراءة شروط الاستخدام وسياسة الخصوصية وإرشادات المجتمع. الشروط والخصوصية مسودتان حتى اكتمال مراجعتهما القانونية.',
        'Berî ku tu xwe tomar bikî, tu dikarî Mercên bikaranînê, Siyaseta nepeniyê û Rêbernameya civakê bixwînî. Merc û Nepenî heta ku nirxandina wan a hiqûqî temam bibe reşnivîs in.',
        None),
    'settingsSafety': ('Safety', 'الأمان', 'Ewlehî', None),
    'policyTerms': ('Terms of use', 'شروط الاستخدام', 'Mercên bikaranînê', None),
    'policyPrivacy': ('Privacy policy', 'سياسة الخصوصية', 'Siyaseta nepeniyê', None),
    'policyGuidelines': ('Community guidelines', 'إرشادات المجتمع', 'Rêbernameya civakê', None),
    'policyNotPublishedTitle': ('Not published yet', 'لم يُنشر بعد', 'Hîn nehatiye weşandin', None),
    'policyNotPublishedBody': ('The RIVALO team has not published this page yet. Check back soon.', 'لم ينشر فريق ريفالو هذه الصفحة بعد. عُد قريبًا.', 'Tîma RIVALO hîn ev rûpel neweşandiye. Zû dîsa binêre.', None),
    'policyPendingTitle': ('Draft: pending legal review', 'مسودة: بانتظار المراجعة القانونية', 'Reşnivîs: li benda nirxandina hiqûqî', None),
    'policyPendingBody': ('This text has not been reviewed by a lawyer yet and may change before RIVALO launches publicly.', 'لم يراجع محامٍ هذا النص بعد، وقد يتغير قبل الإطلاق العام لريفالو.', 'Ev nivîs hîn ji aliyê parêzerekî ve nehatiye nirxandin û dibe ku berî destpêkirina giştî ya RIVALO biguhere.', None),
    'policyTranslationPending': ('Not translated yet. Showing the English text.', 'لم تُترجم بعد. يُعرض النص الإنجليزي.', 'Hîn nehatiye wergerandin. Nivîsa îngilîzî tê nîşandan.', None),
    'policyVersion': ('Version {version} · {date}', 'الإصدار {version} · {date}', 'Guhertoya {version} · {date}', {'version': {'type': 'int'}, 'date': S}),
    'moreActions': ('More', 'المزيد', 'Zêdetir', None),
    'reportPlayerAction': ('Report player', 'الإبلاغ عن اللاعب', 'Lîstikvan rapor bike', None),
    'reportTeamAction': ('Report team', 'الإبلاغ عن الفريق', 'Tîmê rapor bike', None),
    'reportAccountRequired': ('Create a free account to report players or teams.', 'أنشئ حسابًا مجانيًا للإبلاغ عن اللاعبين أو الفرق.', 'Ji bo rapora lîstikvan an tîman hesabekî belaş ava bike.', None),
    'reportPlayerTitle': ('Report {name}', 'الإبلاغ عن {name}', '{name} rapor bike', {'name': S}),
    'reportTeamTitle': ('Report {name}', 'الإبلاغ عن {name}', '{name} rapor bike', {'name': S}),
    'reportAppTitle': ('Report a problem or send feedback', 'الإبلاغ عن مشكلة أو إرسال ملاحظات', 'Pirsgirêkekê rapor bike an nerîna xwe bişîne', None),
    'reportAppIntro': ('Tell the RIVALO team what went wrong or what would make the app better. We add your app version automatically.', 'أخبر فريق ريفالو بما حدث من خطأ أو بما يجعل التطبيق أفضل. نضيف إصدار التطبيق تلقائيًا.', 'Ji tîma RIVALO re bibêje çi xelet çû an çi dê sepanê baştir bike. Em guhertoya sepanê bixweber lê zêde dikin.', None),
    'reportPrivateNote': ('Reports are private. We never tell the other player who reported them.', 'البلاغات سرية. لا نخبر اللاعب الآخر أبدًا بمن أبلغ عنه.', 'Rapor veşartî ne. Em qet ji lîstikvanê din re nabêjin kê rapor kiriye.', None),
    'reportReasonHeader': ('What is wrong?', 'ما المشكلة؟', 'Çi xelet e?', None),
    'reportReasonOffensiveName': ('Offensive name', 'اسم مسيء', 'Navê heqaretê', None),
    'reportReasonOffensivePhoto': ('Offensive photo or logo', 'صورة أو شعار مسيء', 'Wêne an logoya heqaretê', None),
    'reportReasonHarassment': ('Harassment or threats', 'تحرّش أو تهديد', 'Tacîz an gef', None),
    'reportReasonCheating': ('Cheating or fake results', 'غش أو نتائج مزيفة', 'Xapandin an encamên sexte', None),
    'reportReasonImpersonation': ('Pretending to be someone else', 'انتحال شخصية آخر', 'Xwe wekî kesekî din nîşandan', None),
    'reportReasonSpam': ('Spam, scams or betting', 'رسائل مزعجة أو احتيال أو مراهنات', 'Spam, xapandin an bahîs', None),
    'reportReasonOther': ('Something else', 'شيء آخر', 'Tiştekî din', None),
    'reportReasonAppProblem': ('Something isn\'t working', 'شيء لا يعمل', 'Tiştek naxebite', None),
    'reportReasonSuggestion': ('Suggestion', 'اقتراح', 'Pêşniyar', None),
    'reportDetailsLabel': ('Details (optional)', 'التفاصيل (اختياري)', 'Hûrgilî (vebijarkî)', None),
    'reportDetailsLabelRequired': ('Details', 'التفاصيل', 'Hûrgilî', None),
    'reportDetailsHint': ('What happened, and when?', 'ماذا حدث، ومتى؟', 'Çi qewimî, û kengê?', None),
    'reportAppHint': ('What were you doing when it happened?', 'ماذا كنت تفعل عندما حدث ذلك؟', 'Dema ku ew qewimî te çi dikir?', None),
    'reportChooseReason': ('Choose what is wrong.', 'اختر المشكلة.', 'Hilbijêre çi xelet e.', None),
    'reportDetailsRequired': ('Tell us briefly what happened.', 'أخبرنا باختصار بما حدث.', 'Bi kurtî ji me re bibêje çi qewimî.', None),
    'reportSend': ('Send report', 'إرسال البلاغ', 'Raporê bişîne', None),
    'reportSendFeedback': ('Send', 'إرسال', 'Bişîne', None),
    'reportThanksTitle': ('Thanks for telling us', 'شكرًا لإخبارنا', 'Spas ku te ji me re got', None),
    'reportThanksBody': ('Our team will review it. You\'ll get a message in your inbox when they have.', 'سيراجعه فريقنا. ستصلك رسالة في صندوق الوارد بعد المراجعة.', 'Tîma me dê wê binirxîne. Piştî wê tu dê di sindoqa xwe de peyamekê bistînî.', None),
    'reportFeedbackThanks': ('Thanks for your feedback', 'شكرًا على ملاحظاتك', 'Spas ji bo nerîna te', None),
    'reportFeedbackThanksBody': ('The RIVALO team reads every message.', 'يقرأ فريق ريفالو كل رسالة.', 'Tîma RIVALO her peyamê dixwîne.', None),
    'blockAction': ('Block {name}', 'حظر {name}', '{name} asteng bike', {'name': S}),
    'unblockAction': ('Unblock {name}', 'إلغاء حظر {name}', 'Astengkirina {name} rake', {'name': S}),
    'blockConfirmTitle': ('Block {name}?', 'حظر {name}؟', '{name} asteng bikî?', {'name': S}),
    'blockConfirmBody': ('You won\'t find each other in search and can\'t invite each other to teams. They are not told. You can still play in the same tournaments.', 'لن تجدا بعضكما في البحث ولن تتمكنا من دعوة بعضكما إلى الفرق. لن يُبلَّغ بذلك. لا يزال بإمكانكما اللعب في البطولات نفسها.', 'Hûn ê di lêgerînê de hev nebînin û nikarin hev vexwînin tîman. Ji wî re nayê gotin. Hûn hîn jî dikarin di heman tûrnûvayan de bilîzin.', None),
    'blockButton': ('Block', 'حظر', 'Asteng bike', None),
    'blockedToast': ('{name} is blocked.', 'تم حظر {name}.', '{name} hat astengkirin.', {'name': S}),
    'unblockedToast': ('{name} is unblocked.', 'أُلغي حظر {name}.', 'Astengkirina {name} hat rakirin.', {'name': S}),
    'blockedNotice': ('You blocked this player.', 'لقد حظرت هذا اللاعب.', 'Te ev lîstikvan asteng kiriye.', None),
    'blockedTitle': ('Blocked players', 'اللاعبون المحظورون', 'Lîstikvanên astengkirî', None),
    'blockedEmptyTitle': ('No one blocked', 'لا يوجد أحد محظور', 'Kes nehatiye astengkirin', None),
    'blockedExplainer': ('Blocked players don\'t see you in search and can\'t invite you to teams. They are not told.', 'اللاعبون المحظورون لا يرونك في البحث ولا يمكنهم دعوتك إلى الفرق. لا يُبلَّغون بذلك.', 'Lîstikvanên astengkirî te di lêgerînê de nabînin û nikarin te vexwînin tîman. Ji wan re nayê gotin.', None),
    'unblockButton': ('Unblock', 'إلغاء الحظر', 'Astengê rake', None),
}


def load(name):
    return json.loads((L10N / name).read_text(encoding='utf-8'), object_pairs_hook=collections.OrderedDict)


def main():
    en, ar, kmr = load('app_en.arb'), load('app_ar.arb'), load('app_kmr.arb')

    for key, (e, a, k, placeholders) in NEW.items():
        en[key] = e
        if placeholders:
            en['@' + key] = {'placeholders': placeholders}
        ar[key] = a
        kmr[key] = k
    for key, text in AR.items():
        ar.setdefault(key, text)
    for key, text in KMR.items():
        kmr.setdefault(key, text)

    def ordered(target):
        out = collections.OrderedDict((k, v) for k, v in target.items() if k.startswith('@@'))
        for key in en:
            if key.startswith('@'):
                continue
            if key in target:
                out[key] = target[key]
        return out

    (L10N / 'app_en.arb').write_text(json.dumps(en, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    for name, data in (('app_ar.arb', ordered(ar)), ('app_kmr.arb', ordered(kmr))):
        data['@@x-review'] = data['@@x-review'].replace(' Untranslated keys fall back to English.', '')
        (L10N / name).write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')

    keys = [k for k in en if not k.startswith('@')]
    for name, data in (('ar', ar), ('kmr', kmr)):
        missing = [k for k in keys if k not in data]
        print(name, 'missing:', missing)


if __name__ == '__main__':
    main()
