import 'package:geolocator/geolocator.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'strings.dart';

typedef LocationProvider = Future<Map<String, dynamic>> Function();

Future<Map<String, dynamic>> deviceLocation() async {
  try {
    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return {};
    final p = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 10)));
    return {'latitude': p.latitude, 'longitude': p.longitude, 'accuracy': p.accuracy.round()};
  } catch (_) {
    return {};
  }
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(AfradApp(prefs: await SharedPreferences.getInstance()));
}

class AfradApp extends StatefulWidget {
  const AfradApp({super.key, required this.prefs, this.apiFactory, this.location = deviceLocation});
  final SharedPreferences prefs;
  final AfradApi Function(String host, String? token)? apiFactory;
  final LocationProvider location;
  @override
  State<AfradApp> createState() => _AfradAppState();
}

class _AfradAppState extends State<AfradApp> {
  late String lang = widget.prefs.getString('lang') ?? 'en';
  void _toggle() {
    setState(() => lang = lang == 'ar' ? 'en' : 'ar');
    widget.prefs.setString('lang', lang);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Afrad',
      debugShowCheckedModeBanner: false,
      locale: Locale(lang),
      supportedLocales: const [Locale('en'), Locale('ar')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0E2A47)), useMaterial3: true),
      builder: (context, child) => Directionality(textDirection: lang == 'ar' ? TextDirection.rtl : TextDirection.ltr, child: child!),
      home: HomePage(prefs: widget.prefs, lang: lang, onToggleLang: _toggle, apiFactory: widget.apiFactory, location: widget.location),
    );
  }
}

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.prefs, required this.lang, required this.onToggleLang, this.apiFactory, required this.location});
  final SharedPreferences prefs;
  final String lang;
  final VoidCallback onToggleLang;
  final AfradApi Function(String host, String? token)? apiFactory;
  final LocationProvider location;
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  AfradApi? api;
  Map<String, dynamic>? status;
  List<Map<String, dynamic>> history = [];
  List<Map<String, dynamic>> corrs = [];
  List<Map<String, dynamic>> ots = [];
  Map<String, dynamic> leaveData = {};
  String? message;
  @override
  void didUpdateWidget(covariant HomePage old) {
    super.didUpdateWidget(old);
    if (old.lang != widget.lang) message = null;
  }
  bool ok = false;
  bool busy = false;
  final host = TextEditingController();
  final email = TextEditingController();
  final pass = TextEditingController();

  String t(String k) => tr(widget.lang, k);

  @override
  void initState() {
    super.initState();
    final h = widget.prefs.getString('host');
    final tok = widget.prefs.getString('token');
    if (h != null) host.text = h;
    if (h != null && tok != null) {
      api = _make(h, tok);
      _refresh();
    }
  }

  AfradApi _make(String h, String? tok) => widget.apiFactory != null ? widget.apiFactory!(h, tok) : AfradApi(h, token: tok);

  String _msg(Object e) {
    if (e is ApiException) {
      final m = e.message;
      if (m == 'offline') return t('offline');
      if (m.toLowerCase().contains('location')) return t('nolocation');
      return m == 'failed' ? t('fail') : m;
    }
    return t('fail');
  }

  Future<void> _guard(Future<void> Function() fn, {bool success = false}) async {
    setState(() { busy = true; message = null; });
    try {
      await fn();
      if (success) { message = t('recorded'); ok = true; }
    } catch (e) {
      if (e is ApiException && e.unauthorized) {
        await _clearSession();
      } else {
        message = _msg(e);
        ok = false;
      }
    }
    if (mounted) setState(() => busy = false);
  }

  Future<void> _clearSession() async {
    await widget.prefs.remove('token');
    api = null;
    status = null;
  }

  Future<void> _refresh() async {
    final a = api;
    if (a == null) return;
    final s = await a.status();
    final h = await a.history();
    List<Map<String, dynamic>> c = [], o = [];
    try { c = await a.corrections(); } catch (_) {}
    try { o = await a.overtimeRequests(); } catch (_) {}
    Map<String, dynamic> l = {};
    try { l = await a.leave(); } catch (_) {}
    if (mounted) setState(() { status = s; history = h; corrs = c; ots = o; leaveData = l; });
  }

  Future<void> _login() => _guard(() async {
        final h = AfradApi.cleanHost(host.text);
        final a = _make(h, null);
        final tok = await a.login(email.text.trim(), pass.text, 'Afrad mobile app');
        await widget.prefs.setString('host', h);
        await widget.prefs.setString('token', tok);
        api = a;
        pass.clear();
        await _refresh();
      });

  Future<void> _logout() async {
    await api?.logout();
    await _clearSession();
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF0E2A47),
        foregroundColor: Colors.white,
        title: const Text('Afrad'),
        actions: [
          TextButton(key: const Key('lang'), onPressed: widget.onToggleLang, child: Text(t('lang'), style: const TextStyle(color: Colors.white))),
          if (api != null) TextButton(key: const Key('logout'), onPressed: _logout, child: Text(t('signout'), style: const TextStyle(color: Colors.white))),
        ],
      ),
      body: SafeArea(child: ListView(padding: const EdgeInsets.all(16), children: [api == null ? _loginCard() : _dashboard()])),
    );
  }

  Widget _noteBox() => message == null
      ? const SizedBox.shrink()
      : Container(
          key: const Key('note'),
          margin: const EdgeInsets.symmetric(vertical: 8),
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: ok ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2), borderRadius: BorderRadius.circular(10)),
          child: Text(message!),
        );

  Widget _loginCard() => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(t('title'), style: Theme.of(context).textTheme.titleLarge),
            _noteBox(),
            TextField(key: const Key('host'), controller: host, keyboardType: TextInputType.url, decoration: InputDecoration(labelText: t('company'), hintText: t('companyHint'))),
            TextField(key: const Key('email'), controller: email, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: t('email'))),
            TextField(key: const Key('pass'), controller: pass, obscureText: true, decoration: InputDecoration(labelText: t('pass'))),
            const SizedBox(height: 16),
            SizedBox(width: double.infinity, child: FilledButton(key: const Key('signin'), onPressed: busy ? null : _login, child: Text(busy ? t('busy') : t('signin')))),
          ]),
        ),
      );

  String _hm(dynamic m) {
    final n = (m as num?)?.toInt() ?? 0;
    return '${n ~/ 60}:${(n % 60).toString().padLeft(2, '0')}';
  }

  Widget _dashboard() {
    final s = status;
    if (s == null) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(children: [_noteBox(), const CircularProgressIndicator()])));
    final state = s['state'] as String? ?? 'not_checked_in';
    final active = s['active_session'] as Map?;
    final onBreak = active != null && ((active['breaks'] as List?) ?? []).any((b) => (b as Map)['ended_at'] == null);
    final label = state == 'checked_in' ? t('inn') : state == 'checked_out' ? t('outt') : t('notin');
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${t('today')} · ${s['date']}', style: const TextStyle(color: Colors.black54)),
            Text(label, key: const Key('state'), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
            _noteBox(),
            if (state == 'checked_in') ...[
              FilledButton(key: const Key('co'), style: FilledButton.styleFrom(backgroundColor: const Color(0xFFC2410C)), onPressed: busy ? null : () => _record(false), child: Text(t('co'))),
              const SizedBox(height: 8),
              OutlinedButton(key: const Key('break'), onPressed: busy ? null : () => _guard(() async {
                    final id = (active!['id'] as num).toInt();
                    onBreak ? await api!.breakEnd(id) : await api!.breakStart(id);
                    await _refresh();
                  }, success: true), child: Text(onBreak ? t('be') : t('bs'))),
            ] else
              FilledButton(key: const Key('ci'), style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0F9D6B)), onPressed: busy ? null : () => _record(true), child: Text(t('ci'))),
            const SizedBox(height: 8),
            Text(t('locNote'), style: const TextStyle(color: Colors.black54, fontSize: 12)),
          ]),
        ),
      ),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(t('hist'), style: const TextStyle(fontWeight: FontWeight.bold)),
            if (history.isEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(t('empty'))),
            if (history.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text(t('tapDay'), style: const TextStyle(color: Colors.black54, fontSize: 12))),
            for (final r in history)
              InkWell(
                key: Key('day-${r['id']}'),
                onTap: () => _dayActions(r),
                child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
                child: Row(children: [
                  Expanded(flex: 3, child: Text(r['attendance_date'].toString().substring(0, 10))),
                  Expanded(flex: 2, child: Text(_hm(r['total_worked_minutes']))),
                  Expanded(flex: 2, child: Text(((r['late_minutes'] as num?) ?? 0) > 0 ? '${r['late_minutes']} ${t('min')}' : '-')),
                ]),
              )),
          ]),
        ),
      ),
      if (leaveData.isNotEmpty) Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(t('leave'), style: const TextStyle(fontWeight: FontWeight.bold)),
            for (final b in ((leaveData['balances'] as List?) ?? []))
              Padding(padding: const EdgeInsets.only(top: 6), child: Row(children: [
                Expanded(child: Text('${(b as Map)['name']}')),
                Text('${b['remaining']} ${t('remaining')}'),
              ])),
            const SizedBox(height: 8),
            OutlinedButton(key: const Key('req-leave'), onPressed: busy ? null : _leaveDialog, child: Text(t('reqLeave'))),
            for (final r in ((leaveData['requests'] as List?) ?? []))
              Padding(padding: const EdgeInsets.only(top: 8), child: Row(children: [
                Expanded(child: Text('${(r as Map)['start_date'].toString().substring(0, 10)} → ${r['end_date'].toString().substring(0, 10)} (${r['days']} ${t('days')})')),
                if (r['status'] == 'pending') TextButton(key: Key('wd-${r['id']}'), onPressed: busy ? null : () => _guard(() async { await api!.cancelLeave((r['id'] as num).toInt()); await _refresh(); }, success: true), child: Text(t('withdraw')))
                else Text({'approved': t('approved'), 'rejected': t('rejected'), 'cancelled': t('cancelled')}[r['status']] ?? '${r['status']}'),
              ])),
          ]),
        ),
      ),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(t('req'), style: const TextStyle(fontWeight: FontWeight.bold)),
            if (corrs.isEmpty && ots.isEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(t('noReq'), key: const Key('noreq'))),
            for (final c in corrs) _reqRow(t('corr'), c['reason']?.toString() ?? '', c['status']),
            for (final o in ots) _reqRow(t('ot'), '${o['requested_minutes']} ${t('min')}', o['status']),
          ]),
        ),
      ),
    ]);
  }

  Widget _reqRow(String kind, String detail, dynamic status) {
    final st = status?.toString() ?? 'pending';
    final label = {'pending': t('pending'), 'approved': t('approved'), 'rejected': t('rejected')}[st] ?? st;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(children: [
        Expanded(flex: 2, child: Text(kind, style: const TextStyle(fontWeight: FontWeight.w600))),
        Expanded(flex: 4, child: Text(detail, maxLines: 2, overflow: TextOverflow.ellipsis)),
        Expanded(flex: 2, child: Text(label, textAlign: TextAlign.end)),
      ]),
    );
  }

  int? _lastSessionId(Map<String, dynamic> r) {
    final ss = (r['sessions'] as List?) ?? [];
    if (ss.isEmpty) return null;
    return ((ss.last as Map)['id'] as num).toInt();
  }

  Future<void> _dayActions(Map<String, dynamic> r) async {
    final sid = _lastSessionId(r);
    if (sid == null) return;
    final day = r['attendance_date'].toString().substring(0, 10);
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(title: Text(day, style: const TextStyle(fontWeight: FontWeight.bold))),
          ListTile(key: const Key('act-corr'), leading: const Icon(Icons.edit_calendar), title: Text(t('reqCorr')), onTap: () => Navigator.pop(c, 'corr')),
          ListTile(key: const Key('act-ot'), leading: const Icon(Icons.more_time), title: Text(t('reqOt')), onTap: () => Navigator.pop(c, 'ot')),
        ]),
      ),
    );
    if (choice == null || !mounted) return;
    final reason = TextEditingController();
    final minutes = TextEditingController();
    String? inT, outT;
    String? err;
    final send = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(builder: (c, set) {
        Future<void> pick(bool isIn) async {
          final p = await showTimePicker(context: c, initialTime: const TimeOfDay(hour: 9, minute: 0));
          if (p == null) return;
          final v = '${p.hour.toString().padLeft(2, '0')}:${p.minute.toString().padLeft(2, '0')}';
          set(() { if (isIn) { inT = v; } else { outT = v; } });
        }
        return AlertDialog(
          title: Text(choice == 'corr' ? t('reqCorr') : t('reqOt')),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (choice == 'corr') ...[
                OutlinedButton(key: const Key('pick-in'), onPressed: () => pick(true), child: Text(inT == null ? t('inTime') : '${t('inShort')}: $inT')),
                OutlinedButton(key: const Key('pick-out'), onPressed: () => pick(false), child: Text(outT == null ? t('outTime') : '${t('outShort')}: $outT')),
              ] else
                TextField(key: const Key('ot-min'), controller: minutes, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: t('minutes'))),
              TextField(key: const Key('reason'), controller: reason, maxLines: 3, decoration: InputDecoration(labelText: t('reason'))),
              if (err != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(err!, style: const TextStyle(color: Colors.red))),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(t('cancel'))),
            FilledButton(
              key: const Key('send-req'),
              onPressed: () {
                if (choice == 'corr' && reason.text.trim().isEmpty) { set(() => err = t('needReason')); return; }
                final n = int.tryParse(minutes.text.trim());
                if (choice == 'ot' && (n == null || n < 1 || n > 1440)) { set(() => err = t('needMinutes')); return; }
                Navigator.pop(c, true);
              },
              child: Text(t('send')),
            ),
          ],
        );
      }),
    );
    if (send != true || !mounted) return;
    await _guard(() async {
      if (choice == 'corr') {
        await api!.requestCorrection(sid, reason.text.trim(), checkIn: inT == null ? null : '$day $inT:00', checkOut: outT == null ? null : '$day $outT:00');
      } else {
        await api!.requestOvertime(sid, int.parse(minutes.text.trim()), reason.text.trim());
      }
      await _refresh();
    }, success: true);
  }

  Future<void> _leaveDialog() async {
    final balances = ((leaveData['balances'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
    if (balances.isEmpty) { setState(() { message = t('noLeave'); ok = false; }); return; }
    int? typeId;
    DateTime? from, to;
    final reason = TextEditingController();
    String? err;
    String fmt(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final send = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(builder: (c, set) {
        Future<void> pick(bool isFrom) async {
          final now = DateTime.now();
          final d = await showDatePicker(context: c, initialDate: (isFrom ? from : to) ?? now, firstDate: DateTime(now.year - 1), lastDate: DateTime(now.year + 1, 12, 31));
          if (d != null) set(() { if (isFrom) { from = d; to ??= d; } else { to = d; } });
        }
        return AlertDialog(
          title: Text(t('reqLeave')),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              DropdownButtonFormField<int>(key: const Key('leave-type'), decoration: InputDecoration(labelText: t('leaveType')), initialValue: typeId, items: [for (final b in balances) DropdownMenuItem(value: (b['leave_type_id'] as num).toInt(), child: Text('${b['name']}'))], onChanged: (v) => set(() => typeId = v)),
              OutlinedButton(key: const Key('leave-from'), onPressed: () => pick(true), child: Text(from == null ? t('from') : fmt(from!))),
              OutlinedButton(key: const Key('leave-to'), onPressed: () => pick(false), child: Text(to == null ? t('to') : fmt(to!))),
              TextField(key: const Key('leave-reason'), controller: reason, maxLines: 2, decoration: InputDecoration(labelText: t('reason'))),
              if (err != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(err!, style: const TextStyle(color: Colors.red))),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(t('cancel'))),
            FilledButton(key: const Key('send-leave'), onPressed: () {
              if (typeId == null || from == null || to == null) { set(() => err = t('needDates')); return; }
              Navigator.pop(c, true);
            }, child: Text(t('send'))),
          ],
        );
      }),
    );
    if (send != true || !mounted) return;
    await _guard(() async {
      await api!.requestLeave(typeId!, fmt(from!), fmt(to!), reason.text.trim());
      await _refresh();
    }, success: true);
  }

  Future<void> _record(bool inn) => _guard(() async {
        final loc = await widget.location();
        inn ? await api!.checkIn(loc) : await api!.checkOut(loc);
        await _refresh();
      }, success: true);
}
