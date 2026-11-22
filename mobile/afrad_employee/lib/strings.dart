const Map<String, Map<String, String>> _t = {
  'en': {
    'title': 'Employee attendance', 'company': 'Company address', 'companyHint': 'for example acme.afrad.example', 'email': 'Email', 'pass': 'Password',
    'signin': 'Sign in', 'signout': 'Sign out', 'today': 'Today', 'notin': 'Not checked in', 'inn': 'Checked in', 'outt': 'Checked out',
    'ci': 'Check in', 'co': 'Check out', 'bs': 'Start break', 'be': 'End break', 'hist': 'Recent days', 'worked': 'Worked (h:mm)', 'late': 'Late',
    'min': 'min', 'empty': 'No records yet.', 'busy': 'Please wait...', 'recorded': 'Recorded.', 'offline': 'No connection. Your action was not recorded.',
    'nolocation': 'Your company requires location. Allow location access and try again.', 'fail': 'Something went wrong. Try again.', 'lang': 'العربية',
    'locNote': 'Location is sent with check-in when your company asks for it.',
  },
  'ar': {
    'title': 'حضور الموظف', 'company': 'عنوان الشركة', 'companyHint': 'مثال acme.afrad.example', 'email': 'الإيميل', 'pass': 'كلمة السر',
    'signin': 'دخول', 'signout': 'خروج', 'today': 'النهارده', 'notin': 'لسه ما سجلتش حضور', 'inn': 'حاضر', 'outt': 'سجلت انصراف',
    'ci': 'تسجيل حضور', 'co': 'تسجيل انصراف', 'bs': 'بدء استراحة', 'be': 'إنهاء استراحة', 'hist': 'آخر الأيام', 'worked': 'ساعات الشغل', 'late': 'تأخير',
    'min': 'دقيقة', 'empty': 'مفيش سجلات لسه.', 'busy': 'استنى شوية...', 'recorded': 'اتسجل.', 'offline': 'مفيش اتصال. العملية ما اتسجلتش.',
    'nolocation': 'الشركة بتطلب الموقع. اسمح بالوصول للموقع وجرب تاني.', 'fail': 'حصلت مشكلة. جرب تاني.', 'lang': 'English',
    'locNote': 'الموقع بيتبعت مع تسجيل الحضور لو الشركة طالباه.',
  },
};

String tr(String lang, String key) => _t[lang]?[key] ?? _t['en']![key] ?? key;
