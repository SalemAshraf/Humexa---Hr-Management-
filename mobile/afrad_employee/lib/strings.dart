const Map<String, Map<String, String>> _t = {
  'en': {
    'title': 'Employee attendance', 'company': 'Company address', 'companyHint': 'for example acme.afrad.example', 'email': 'Email', 'pass': 'Password',
    'signin': 'Sign in', 'signout': 'Sign out', 'today': 'Today', 'notin': 'Not checked in', 'inn': 'Checked in', 'outt': 'Checked out',
    'ci': 'Check in', 'co': 'Check out', 'bs': 'Start break', 'be': 'End break', 'hist': 'Recent days', 'worked': 'Worked (h:mm)', 'late': 'Late',
    'min': 'min', 'empty': 'No records yet.', 'busy': 'Please wait...', 'recorded': 'Recorded.', 'offline': 'No connection. Your action was not recorded.',
    'nolocation': 'Your company requires location. Allow location access and try again.', 'fail': 'Something went wrong. Try again.', 'lang': 'العربية',
    'req': 'Requests', 'reqCorr': 'Request a correction', 'reqOt': 'Request overtime', 'reason': 'Reason', 'minutes': 'Overtime minutes', 'send': 'Send', 'cancel': 'Cancel', 'noReq': 'No requests yet.',
    'pending': 'Pending', 'approved': 'Approved', 'rejected': 'Rejected', 'corr': 'Correction', 'ot': 'Overtime', 'inTime': 'Check-in time (optional)', 'outTime': 'Check-out time (optional)', 'tapDay': 'Tap a day to request a correction or overtime.', 'needReason': 'Enter a reason.', 'needMinutes': 'Enter minutes from 1 to 1440.',
    'leave': 'Leave', 'reqLeave': 'Request leave', 'leaveType': 'Leave type', 'from': 'From', 'to': 'To', 'remaining': 'left', 'days': 'd', 'withdraw': 'Withdraw', 'cancelled': 'Withdrawn', 'needDates': 'Choose the type and both dates.', 'noLeave': 'No leave types set up yet.',
    'inShort': 'Check-in', 'outShort': 'Check-out',
    'locNote': 'Location is sent with check-in when your company asks for it.',
  },
  'ar': {
    'title': 'حضور الموظف', 'company': 'عنوان الشركة', 'companyHint': 'مثال acme.afrad.example', 'email': 'الإيميل', 'pass': 'كلمة السر',
    'signin': 'دخول', 'signout': 'خروج', 'today': 'النهارده', 'notin': 'لسه ما سجلتش حضور', 'inn': 'حاضر', 'outt': 'سجلت انصراف',
    'ci': 'تسجيل حضور', 'co': 'تسجيل انصراف', 'bs': 'بدء استراحة', 'be': 'إنهاء استراحة', 'hist': 'آخر الأيام', 'worked': 'ساعات الشغل', 'late': 'تأخير',
    'min': 'دقيقة', 'empty': 'مفيش سجلات لسه.', 'busy': 'استنى شوية...', 'recorded': 'اتسجل.', 'offline': 'مفيش اتصال. العملية ما اتسجلتش.',
    'nolocation': 'الشركة بتطلب الموقع. اسمح بالوصول للموقع وجرب تاني.', 'fail': 'حصلت مشكلة. جرب تاني.', 'lang': 'English',
    'req': 'الطلبات', 'reqCorr': 'طلب تصحيح', 'reqOt': 'طلب وقت إضافي', 'reason': 'السبب', 'minutes': 'دقائق الوقت الإضافي', 'send': 'إرسال', 'cancel': 'إلغاء', 'noReq': 'لا توجد طلبات بعد.',
    'pending': 'قيد المراجعة', 'approved': 'تمت الموافقة', 'rejected': 'مرفوض', 'corr': 'تصحيح', 'ot': 'وقت إضافي', 'inTime': 'وقت الحضور (اختياري)', 'outTime': 'وقت الانصراف (اختياري)', 'tapDay': 'اضغط على يوم لطلب تصحيح أو وقت إضافي.', 'needReason': 'اكتب السبب.', 'needMinutes': 'اكتب عدد دقائق من 1 إلى 1440.',
    'leave': 'الإجازات', 'reqLeave': 'طلب إجازة', 'leaveType': 'نوع الإجازة', 'from': 'من', 'to': 'إلى', 'remaining': 'متبقي', 'days': 'يوم', 'withdraw': 'سحب الطلب', 'cancelled': 'تم السحب', 'needDates': 'اختر النوع والتاريخين.', 'noLeave': 'لا توجد أنواع إجازات بعد.',
    'inShort': 'الحضور', 'outShort': 'الانصراف',
    'locNote': 'الموقع بيتبعت مع تسجيل الحضور لو الشركة طالباه.',
  },
};

String tr(String lang, String key) => _t[lang]?[key] ?? _t['en']![key] ?? key;
