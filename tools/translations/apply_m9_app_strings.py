"""
Milestone 9: seller marketplace strings for the app in English, Arabic and Kurdish (Kurmanji).
Arabic and Kurdish are drafts pending native review. Run from the repository root:
    py tools/translations/apply_m9_app_strings.py
"""
import collections
import json
import pathlib

L10N = pathlib.Path(__file__).resolve().parents[2] / 'mobile' / 'lib' / 'core' / 'l10n'
S = {'type': 'String'}

NEW = {
    # Store: who sells a product
    'storeSoldBy': ('Sold by {name}', 'يبيعه {name}', 'Firoşkar: {name}', {'name': S}),
    'storeOfficialProduct': ('Official RIVALO product', 'منتج رسمي من RIVALO', 'Berhema fermî ya RIVALO', None),
    'storeApprovedSeller': ('Approved seller', 'بائع معتمد', 'Firoşkarê pejirandî', None),
    'storeSellerNote': (
        'Sold by an approved seller. RIVALO keeps the stock, hands it over and takes the payment, so pickup, delivery and returns work as for official products.',
        'يبيعه بائع معتمد. تحتفظ RIVALO بالمخزون وتسلّم المنتج وتستلم الدفع، لذا يتم الاستلام والتوصيل والإرجاع كما في المنتجات الرسمية.',
        'Firoşkarekî pejirandî difiroşe. RIVALO stokê diparêze, berhemê radestî te dike û pereyê distîne, ji ber vê yekê wergirtin, gihandin û vegerandin wek berhemên fermî dixebitin.',
        None),
    'storeShopsTitle': ('Shops', 'المتاجر', 'Firotgeh', None),
    'storeShopProducts': ('On sale', 'معروض للبيع', 'Ji bo firotinê', None),
    'storeShopEmpty': ('Nothing on sale right now', 'لا شيء معروض للبيع حاليًا', 'Niha tiştek ji bo firotinê tune', None),
    'storeShopEmptyBody': ('Check back soon.', 'عُد لاحقًا.', 'Paşê dîsa binêre.', None),
    'storeShopUnavailableTitle': ('This shop is not available', 'هذا المتجر غير متاح', 'Ev firotgeh ne berdest e', None),
    'storeShopUnavailableBody': ('It may be paused or closed.', 'قد يكون متوقفًا مؤقتًا أو مغلقًا.', 'Dibe ku bi demkî hatibe rawestandin an girtin.', None),

    # Sell on RIVALO
    'sellTitle': ('Sell on RIVALO', 'بِع عبر RIVALO', 'Li ser RIVALO bifiroşe', None),
    'sellTeaser': ('Run a sports shop or make gear? Apply to sell to RIVALO players.', 'تملك متجرًا رياضيًا أو تصنع معدات؟ قدّم طلبًا للبيع للاعبي RIVALO.', 'Firotgeheke werzişî ya te heye an tu alavan çêdikî? Ji bo firotina ji lîstikvanên RIVALO re serlêdanê bike.', None),
    'sellAccountRequired': ('Create a free account to apply to sell.', 'أنشئ حسابًا مجانيًا لتقديم طلب البيع.', 'Ji bo serlêdana firotinê hesabekî belaş ava bike.', None),
    'sellIntro': (
        'Approved shops and makers can sell their products in the RIVALO store.',
        'يمكن للمتاجر والصنّاع المعتمدين بيع منتجاتهم في متجر RIVALO.',
        'Firotgeh û çêkerên pejirandî dikarin berhemên xwe li firotgeha RIVALO bifiroşin.',
        None),
    'sellHowTitle': ('How it works', 'كيف يعمل', 'Çawa dixebite', None),
    'sellHow1': (
        'Apply here. RIVALO staff check who you are and sign an agreement with you, including the commission.',
        'قدّم طلبك هنا. يتحقق موظفو RIVALO من هويتك ويوقّعون معك اتفاقية تشمل العمولة.',
        'Li vir serlêdanê bike. Karmendên RIVALO kontrol dikin tu kî yî û bi te re peymanekê îmze dikin, komîsyon jî tê de.',
        None),
    'sellHow2': (
        'Add your products in the Seller Hub on a computer. Staff review each one before it goes on sale.',
        'أضف منتجاتك في مركز البائعين من حاسوب. يراجع الموظفون كل منتج قبل عرضه للبيع.',
        'Berhemên xwe ji komputerê li Navenda Firoşkaran zêde bike. Karmend her yekê berî firotinê dinirxînin.',
        None),
    'sellHow3': (
        'Bring your stock to RIVALO. We hand it to customers, take the cash and pay you what you earned, minus the commission.',
        'أحضر مخزونك إلى RIVALO. نسلّمه للعملاء ونستلم الثمن نقدًا وندفع لك أرباحك بعد خصم العمولة.',
        'Stoka xwe bîne RIVALO. Em wê radestî kirrîyaran dikin, pereyê kaş distînin û qezenca te, bêyî komîsyonê, didin te.',
        None),
    'sellApply': ('Apply to sell', 'قدّم طلب البيع', 'Ji bo firotinê serlêdanê bike', None),
    'sellApplyAgain': ('Apply again', 'قدّم طلبًا جديدًا', 'Dîsa serlêdanê bike', None),
    'sellApplyTitle': ('Apply to sell', 'طلب البيع', 'Serlêdana firotinê', None),
    'sellBusinessName': ('Shop or business name', 'اسم المتجر أو النشاط التجاري', 'Navê firotgehê an karsaziyê', None),
    'sellPhone': ('Phone number', 'رقم الهاتف', 'Hejmara telefonê', None),
    'sellPhoneHelper': ('Only RIVALO staff see it.', 'يراه موظفو RIVALO فقط.', 'Tenê karmendên RIVALO wê dibînin.', None),
    'sellPhoneInvalid': ('Enter a phone number (digits, spaces, + and - only).', 'أدخل رقم هاتف (أرقام ومسافات و+ و- فقط).', 'Hejmareke telefonê binivîse (tenê hejmar, valahî, + û -).', None),
    'sellCity': ('City', 'المدينة', 'Bajar', None),
    'sellCategories': ('What would you sell?', 'ماذا ستبيع؟', 'Tu dê çi bifroşî?', None),
    'sellCategoriesRequired': ('Choose at least one.', 'اختر واحدًا على الأقل.', 'Herî kêm yekê hilbijêre.', None),
    'sellDescription': ('Tell us about your products', 'أخبرنا عن منتجاتك', 'Derbarê berhemên xwe de ji me re bibêje', None),
    'sellDescriptionHelper': ('Brands, what you make, where you sell today.', 'العلامات التجارية، ما تصنعه، وأين تبيع حاليًا.', 'Marke, tu çi çêdikî, tu niha li ku difiroşî.', None),
    'sellDescriptionTooShort': ('Write at least 20 characters.', 'اكتب 20 حرفًا على الأقل.', 'Herî kêm 20 tîpan binivîse.', None),
    'sellLink': ('Shop page or social profile (optional)', 'صفحة المتجر أو حساب التواصل (اختياري)', 'Rûpela firotgehê an profîla civakî (ne mecbûrî)', None),
    'sellTermsNotice': ('Before you apply, read how selling on RIVALO works.', 'قبل التقديم، اقرأ كيف يعمل البيع عبر RIVALO.', 'Berî serlêdanê, bixwîne ka firotin li ser RIVALO çawa dixebite.', None),
    'sellReadTerms': ('Selling on RIVALO: terms', 'البيع عبر RIVALO: الشروط', 'Firotin li ser RIVALO: merc', None),
    'sellSubmit': ('Send application', 'إرسال الطلب', 'Serlêdanê bişîne', None),
    'sellSubmitted': ('Application sent.', 'تم إرسال الطلب.', 'Serlêdan hat şandin.', None),
    'sellPendingPill': ('Under review', 'قيد المراجعة', 'Tê nirxandin', None),
    'sellPendingTitle': ('Your application is being reviewed', 'طلبك قيد المراجعة', 'Serlêdana te tê nirxandin', None),
    'sellPendingBody': (
        'RIVALO staff will contact you about the seller agreement. You will get a message in your inbox when they decide.',
        'سيتواصل معك موظفو RIVALO بشأن اتفاقية البائع. ستصلك رسالة في صندوق الوارد عند اتخاذ القرار.',
        'Karmendên RIVALO dê derbarê peymana firoşkar de bi te re têkilî daynin. Dema ku biryar bidin, tu dê di qutiya peyaman de peyamekê bistînî.',
        None),
    'sellSentOn': ('Sent {date}', 'أُرسل في {date}', 'Hat şandin {date}', {'date': S}),
    'sellWithdraw': ('Withdraw application', 'سحب الطلب', 'Serlêdanê paşve bikişîne', None),
    'sellWithdrawConfirm': ('Withdraw your application?', 'هل تريد سحب طلبك؟', 'Tu serlêdana xwe paşve dikişînî?', None),
    'sellRejectedTitle': ('Your last application was not approved', 'لم تتم الموافقة على طلبك الأخير', 'Serlêdana te ya dawî nehat pejirandin', None),
    'sellRejectedNoReason': ('No reason was given. You can contact support from Settings.', 'لم يُذكر سبب. يمكنك التواصل مع الدعم من الإعدادات.', 'Sedemek nehat dayîn. Tu dikarî ji Mîhengan bi piştgiriyê re têkilî daynî.', None),
    'sellClosedTitle': ('Selling is not open yet', 'البيع غير متاح بعد', 'Firotin hîn ne vekirî ye', None),
    'sellClosedBody': ('RIVALO is not accepting new sellers right now.', 'لا تقبل RIVALO بائعين جددًا حاليًا.', 'RIVALO niha firoşkarên nû qebûl nake.', None),
    'sellApprovedTitle': ('You sell on RIVALO as {shop}', 'أنت تبيع عبر RIVALO باسم {shop}', 'Tu li ser RIVALO wek {shop} difiroşî', {'shop': S}),
    'sellShopPaused': ('Shop paused', 'المتجر متوقف مؤقتًا', 'Firotgeh bi demkî rawestiyaye', None),
    'sellShopClosed': ('Shop closed', 'المتجر مغلق', 'Firotgeh girtî ye', None),
    'sellHubTitle': ('Seller Hub', 'مركز البائعين', 'Navenda Firoşkaran', None),
    'sellHubBody': (
        'Manage your products, sales and statement in the Seller Hub. Sign in on a computer with your RIVALO account and your authenticator app.',
        'أدِر منتجاتك ومبيعاتك وكشف حسابك في مركز البائعين. سجّل الدخول من حاسوب بحسابك في RIVALO وتطبيق المصادقة.',
        'Berhem, firotin û hesabnameya xwe li Navenda Firoşkaran birêve bibe. Ji komputerê bi hesabê xwe yê RIVALO û sepana xwe ya piştrastkirinê têkeve.',
        None),
    'sellHubAddress': ('Seller Hub address', 'عنوان مركز البائعين', 'Navnîşana Navenda Firoşkaran', None),
    'sellCopyAddress': ('Copy address', 'نسخ العنوان', 'Navnîşanê kopî bike', None),
    'sellCopied': ('Address copied.', 'تم نسخ العنوان.', 'Navnîşan hat kopîkirin.', None),
    'sellViewShop': ('See your shop in the store', 'شاهد متجرك في المتجر', 'Firotgeha xwe li firotgehê bibîne', None),
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
    for name, data in (('app_en.arb', en), ('app_ar.arb', ar), ('app_kmr.arb', kmr)):
        (L10N / name).write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    print('added', len(NEW))


if __name__ == '__main__':
    main()
