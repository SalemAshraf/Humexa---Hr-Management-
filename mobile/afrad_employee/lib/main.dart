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
    if (mounted) setState(() { status = s; history = h; });
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
            for (final r in history)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(children: [
                  Expanded(flex: 3, child: Text(r['attendance_date'].toString().substring(0, 10))),
                  Expanded(flex: 2, child: Text(_hm(r['total_worked_minutes']))),
                  Expanded(flex: 2, child: Text(((r['late_minutes'] as num?) ?? 0) > 0 ? '${r['late_minutes']} ${t('min')}' : '-')),
                ]),
              ),
          ]),
        ),
      ),
    ]);
  }

  Future<void> _record(bool inn) => _guard(() async {
        final loc = await widget.location();
        inn ? await api!.checkIn(loc) : await api!.checkOut(loc);
        await _refresh();
      }, success: true);
}
