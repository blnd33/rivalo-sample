"""
Milestone 8: store strings for the app in English, Arabic and Kurdish (Kurmanji). Arabic and
Kurdish are drafts pending native review. Run from the repository root:
    py tools/translations/apply_m8_app_strings.py
"""
import collections
import json
import pathlib

L10N = pathlib.Path(__file__).resolve().parents[2] / 'mobile' / 'lib' / 'core' / 'l10n'
S = {'type': 'String'}
I = {'type': 'int'}

NEW = {
    'tabStore': ('Store', 'المتجر', 'Firotgeh', None),
    'storeTitle': ('Store', 'المتجر', 'Firotgeh', None),
    'storeFeatured': ('Featured', 'مميز', 'Taybet', None),
    'storeCatJerseys': ('Jerseys', 'القمصان', 'Kiras', None),
    'storeCatApparel': ('Apparel', 'الملابس', 'Cil û berg', None),
    'storeCatAccessories': ('Accessories', 'الإكسسوارات', 'Aksesuar', None),
    'storeCatEquipment': ('Equipment', 'المعدات', 'Alav', None),
    'storeEmptyTitle': ('Nothing here yet', 'لا شيء هنا بعد', 'Hîn tiştek li vir tune', None),
    'storeEmptyBody': ('Official RIVALO products will appear here.', 'ستظهر منتجات ريفالو الرسمية هنا.', 'Berhemên fermî yên RIVALO dê li vir xuya bibin.', None),
    'storePriceFrom': ('From {price}', 'ابتداءً من {price}', 'Ji {price}', {'price': S}),
    'storeSoldOut': ('Sold out', 'نفدت الكمية', 'Qediya', None),
    'storeOnlyLeft': ('{count, plural, =1{Only 1 left} other{Only {count} left}}', '{count, plural, =1{بقيت قطعة واحدة فقط} other{بقي {count} فقط}}', '{count, plural, =1{Tenê 1 maye} other{Tenê {count} mane}}', {'count': I}),
    'storeChooseSize': ('Choose a size', 'اختر المقاس', 'Mezinahiyê hilbijêre', None),
    'storeOption': ('Option', 'الخيار', 'Vebijark', None),
    'storeAbout': ('About this item', 'عن هذا المنتج', 'Derbarê vê berhemê', None),
    'storeAddToBag': ('Add to bag', 'أضف إلى الحقيبة', 'Têxe çenteyê', None),
    'storeAddedToBag': ('Added to your bag.', 'أُضيف إلى حقيبتك.', 'Hat zêdekirin li çenteyê te.', None),
    'storeViewBag': ('View bag', 'عرض الحقيبة', 'Çenteyê bibîne', None),
    'storeAccountRequired': ('Create a free account to buy from the RIVALO store.', 'أنشئ حسابًا مجانيًا للشراء من متجر ريفالو.', 'Ji bo kirîna ji firotgeha RIVALO hesabekî belaş ava bike.', None),
    'storeCashNotice': ('Pay in cash when you collect your order or when it is delivered. There is no card payment in the app.', 'ادفع نقدًا عند استلام طلبك أو عند توصيله. لا يوجد دفع بالبطاقة في التطبيق.', 'Dema ku tu fermana xwe distînî an dema ku tê gihandin, bi pereyê kaş bide. Di sepanê de dayîna bi kartê tune.', None),
    'quantityLabel': ('Quantity', 'الكمية', 'Hejmar', None),
    'quantityFor': ('Quantity of {name}', 'كمية {name}', 'Hejmara {name}', {'name': S}),
    'quantityDecrease': ('Fewer', 'أقل', 'Kêmtir', None),
    'quantityIncrease': ('More', 'أكثر', 'Zêdetir', None),
    'bagTitle': ('Bag', 'الحقيبة', 'Çente', None),
    'bagWithCount': ('{count, plural, =1{Bag, 1 item} other{Bag, {count} items}}', '{count, plural, =1{الحقيبة، منتج واحد} other{الحقيبة، {count} منتجات}}', '{count, plural, =1{Çente, 1 berhem} other{Çente, {count} berhem}}', {'count': I}),
    'bagEmptyTitle': ('Your bag is empty', 'حقيبتك فارغة', 'Çenteyê te vala ye', None),
    'bagEmptyBody': ('Add jerseys, apparel or equipment from the store.', 'أضف قمصانًا أو ملابس أو معدات من المتجر.', 'Ji firotgehê kiras, cil an alavan zêde bike.', None),
    'bagBrowse': ('Browse the store', 'تصفّح المتجر', 'Li firotgehê bigere', None),
    'bagSubtotal': ('Subtotal', 'المجموع الفرعي', 'Berhevoka jêrîn', None),
    'bagCheckout': ('Checkout', 'إتمام الطلب', 'Fermanê temam bike', None),
    'bagFixFirst': ('Something in your bag changed. Update or remove it first.', 'تغيّر شيء في حقيبتك. حدّثه أو أزله أولًا.', 'Tiştek di çenteyê te de guherî. Pêşî wê nû bike an rake.', None),
    'bagOnlyLeft': ('{count, plural, =0{Sold out} =1{Only 1 left: lower the quantity} other{Only {count} left: lower the quantity}}', '{count, plural, =0{نفدت الكمية} =1{بقيت قطعة واحدة فقط: قلّل الكمية} other{بقي {count} فقط: قلّل الكمية}}', '{count, plural, =0{Qediya} =1{Tenê 1 maye: hejmarê kêm bike} other{Tenê {count} mane: hejmarê kêm bike}}', {'count': I}),
    'bagUnavailable': ('No longer available', 'لم يعد متاحًا', 'Êdî ne berdest e', None),
    'bagRemove': ('Remove', 'إزالة', 'Rake', None),
    'checkoutTitle': ('Checkout', 'إتمام الطلب', 'Temamkirina fermanê', None),
    'checkoutPickup': ('Pickup', 'استلام', 'Wergirtin', None),
    'checkoutDelivery': ('Delivery', 'توصيل', 'Gihandin', None),
    'checkoutNoDelivery': ('Delivery is not available yet. Choose pickup.', 'التوصيل غير متاح بعد. اختر الاستلام.', 'Gihandin hîn ne berdest e. Wergirtinê hilbijêre.', None),
    'checkoutPickupWhere': ('Where to collect', 'مكان الاستلام', 'Cihê wergirtinê', None),
    'checkoutPickupFooter': ('We tell you in your inbox when your order is ready.', 'سنخبرك في صندوق الوارد عندما يصبح طلبك جاهزًا.', 'Dema ku fermana te amade be, em ê di sindoqa te de ji te re bibêjin.', None),
    'checkoutDeliveryArea': ('Delivery area', 'منطقة التوصيل', 'Herêma gihandinê', None),
    'checkoutDeliveryFooter': ('We deliver only to these areas. The fee is added to your total.', 'نوصل إلى هذه المناطق فقط. تُضاف الرسوم إلى المجموع.', 'Em tenê digihînin van herêman. Heq li ser berhevokê tê zêdekirin.', None),
    'checkoutFree': ('Free', 'مجاني', 'Belaş', None),
    'checkoutRecipient': ('Recipient name', 'اسم المستلم', 'Navê wergir', None),
    'checkoutPhone': ('Phone number', 'رقم الهاتف', 'Hejmara telefonê', None),
    'checkoutAddress': ('Address', 'العنوان', 'Navnîşan', None),
    'checkoutAddressHelper': ('Street, building, landmark. Only the store team sees it.', 'الشارع، المبنى، معلم قريب. لا يراه إلا فريق المتجر.', 'Kolan, avahî, nîşaneyek. Tenê tîma firotgehê wê dibîne.', None),
    'checkoutNote': ('Note for the store (optional)', 'ملاحظة للمتجر (اختياري)', 'Têbînî ji bo firotgehê (vebijarkî)', None),
    'checkoutSummary': ('Summary', 'الملخص', 'Kurte', None),
    'checkoutDeliveryFee': ('Delivery', 'التوصيل', 'Gihandin', None),
    'checkoutTotal': ('Total', 'المجموع', 'Berhevok', None),
    'checkoutPayOnPickup': ('Pay in cash on pickup', 'الدفع نقدًا عند الاستلام', 'Dema wergirtinê bi kaş bide', None),
    'checkoutPayOnDelivery': ('Pay in cash on delivery', 'الدفع نقدًا عند التوصيل', 'Dema gihandinê bi kaş bide', None),
    'checkoutPayBody': ('You pay {amount} in cash when you receive your order. Nothing is charged now.', 'تدفع {amount} نقدًا عند استلام طلبك. لا يُخصم أي مبلغ الآن.', 'Dema ku tu fermana xwe distînî {amount} bi kaş didî. Niha tiştek nayê girtin.', {'amount': S}),
    'checkoutChoosePickup': ('Choose where to collect your order.', 'اختر مكان استلام طلبك.', 'Hilbijêre fermana xwe li ku werdigirî.', None),
    'checkoutChooseArea': ('Choose a delivery area.', 'اختر منطقة التوصيل.', 'Herêmeke gihandinê hilbijêre.', None),
    'checkoutDeliveryDetails': ('Enter the name, phone number and address for delivery.', 'أدخل الاسم ورقم الهاتف والعنوان للتوصيل.', 'Ji bo gihandinê nav, hejmara telefonê û navnîşanê binivîse.', None),
    'checkoutPlace': ('Place order', 'تأكيد الطلب', 'Fermanê bide', None),
    'ordersTitle': ('My orders', 'طلباتي', 'Fermanên min', None),
    'ordersEmptyTitle': ('No orders yet', 'لا توجد طلبات بعد', 'Hîn ferman tune', None),
    'ordersEmptyBody': ('Orders you place in the store appear here.', 'تظهر هنا الطلبات التي تقدمها في المتجر.', 'Fermanên ku tu di firotgehê de didî li vir xuya dibin.', None),
    'ordersItems': ('{count, plural, =1{1 item} other{{count} items}}', '{count, plural, =1{منتج واحد} other{{count} منتجات}}', '{count, plural, =1{1 berhem} other{{count} berhem}}', {'count': I}),
    'orderStatusPlaced': ('Placed', 'تم الطلب', 'Hat dayîn', None),
    'orderStatusConfirmed': ('Confirmed', 'مؤكد', 'Hat piştrastkirin', None),
    'orderStatusReady': ('Ready for pickup', 'جاهز للاستلام', 'Ji bo wergirtinê amade ye', None),
    'orderStatusOutForDelivery': ('Out for delivery', 'في الطريق إليك', 'Di rê de ye', None),
    'orderStatusCompleted': ('Completed', 'مكتمل', 'Temam bû', None),
    'orderStatusCancelled': ('Cancelled', 'ملغى', 'Hat betalkirin', None),
    'orderPlacedTitle': ('Order placed', 'تم تقديم الطلب', 'Ferman hat dayîn', None),
    'orderPlacedPickup': ('We will tell you in your inbox when it is ready to collect. Pay in cash on pickup.', 'سنخبرك في صندوق الوارد عندما يصبح جاهزًا للاستلام. ادفع نقدًا عند الاستلام.', 'Dema ku ji bo wergirtinê amade be em ê di sindoqa te de ji te re bibêjin. Dema wergirtinê bi kaş bide.', None),
    'orderPlacedDelivery': ('We will tell you in your inbox when it is on its way. Pay in cash on delivery.', 'سنخبرك في صندوق الوارد عندما يكون في الطريق. ادفع نقدًا عند التوصيل.', 'Dema ku di rê de be em ê di sindoqa te de ji te re bibêjin. Dema gihandinê bi kaş bide.', None),
    'orderPlacedOn': ('Placed {date}', 'طُلب في {date}', 'Hat dayîn {date}', {'date': S}),
    'orderCancelledByYou': ('You cancelled this order.', 'لقد ألغيت هذا الطلب.', 'Te ev ferman betal kir.', None),
    'orderCancelledByStore': ('The store cancelled this order.', 'ألغى المتجر هذا الطلب.', 'Firotgehê ev ferman betal kir.', None),
    'orderItems': ('Items', 'المنتجات', 'Berhem', None),
    'orderReturned': ('{count} returned', 'أُرجع {count}', '{count} hatin vegerandin', {'count': I}),
    'orderPayment': ('Payment', 'الدفع', 'Dayîn', None),
    'orderPaid': ('Paid in cash', 'مدفوع نقدًا', 'Bi kaş hatiye dayîn', None),
    'orderRefunded': ('Refunded', 'مُسترد', 'Hatiye vegerandin', None),
    'orderPartlyRefunded': ('Paid · {amount} refunded', 'مدفوع · أُعيد {amount}', 'Hatiye dayîn · {amount} hat vegerandin', {'amount': S}),
    'orderReceipt': ('Receipt {reference}', 'الإيصال {reference}', 'Wesl {reference}', {'reference': S}),
    'orderCancel': ('Cancel order', 'إلغاء الطلب', 'Fermanê betal bike', None),
    'orderCancelTitle': ('Cancel this order?', 'إلغاء هذا الطلب؟', 'Vê fermanê betal bikî?', None),
    'orderCancelBody': ('The items go back on sale. You can order again later.', 'ستعود المنتجات للبيع. يمكنك الطلب مرة أخرى لاحقًا.', 'Berhem dîsa ji bo firotinê vedigerin. Tu dikarî paşê dîsa ferman bidî.', None),
    'orderCancelled': ('Order cancelled.', 'أُلغي الطلب.', 'Ferman hat betalkirin.', None),
    'notifOrders': ('Store orders', 'طلبات المتجر', 'Fermanên firotgehê', None),
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
    for name, data, indent in (('app_en.arb', en, 2), ('app_ar.arb', ar, 2), ('app_kmr.arb', kmr, 2)):
        (L10N / name).write_text(json.dumps(data, ensure_ascii=False, indent=indent) + '\n', encoding='utf-8', newline='\n')
    print('added', len(NEW))


if __name__ == '__main__':
    main()
