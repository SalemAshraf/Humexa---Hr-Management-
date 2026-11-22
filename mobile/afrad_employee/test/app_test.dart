import 'dart:convert';

import 'package:afrad_employee/api.dart';
import 'package:afrad_employee/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:material_ui/material_ui.dart';
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FakeServer {
  String state = 'not_checked_in';
  final List<String> calls = [];
  Map<String, dynamic>? lastBody;

  MockClient client() => MockClient((req) async {
        calls.add('${req.method} ${req.url.path}');
        if (req.body.isNotEmpty) lastBody = jsonDecode(req.body) as Map<String, dynamic>;
        if (req.url.path == '/api/v1/auth/login') {
          final b = jsonDecode(req.body) as Map;
          if (b['password'] != 'right') return http.Response(jsonEncode({'message': 'The given data was invalid.', 'errors': {'credentials': ['The supplied credentials are invalid.']}}), 422);
          return http.Response(jsonEncode({'data': {'token': 'tok123'}}), 200);
        }
        if (req.headers['Authorization'] != 'Bearer tok123') return http.Response('{}', 401);
        if (req.url.path.endsWith('/status')) {
          return http.Response(jsonEncode({'data': {'date': '2026-11-22', 'state': state, 'active_session': state == 'checked_in' ? {'id': 7, 'breaks': []} : null}}), 200);
        }
        if (req.url.path.endsWith('/history')) {
          return http.Response(jsonEncode({'data': [{'attendance_date': '2026-11-21T00:00:00Z', 'total_worked_minutes': 468, 'late_minutes': 12}]}), 200);
        }
        if (req.url.path.endsWith('/check-in')) { state = 'checked_in'; return http.Response('{}', 201); }
        if (req.url.path.endsWith('/check-out')) { state = 'checked_out'; return http.Response('{}', 200); }
        return http.Response('{}', 200);
      });
}

void main() {
  test('company address is cleaned', () {
    expect(AfradApi.cleanHost(' HTTPS://Acme.Example.com/path '), 'acme.example.com');
  });

  testWidgets('sign in, check in with location, check out, wrong password, Arabic', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final server = FakeServer();
    await tester.pumpWidget(AfradApp(
      prefs: prefs,
      apiFactory: (h, t) => AfradApi(h, token: t, client: server.client()),
      location: () async => {'latitude': 30.04, 'longitude': 31.23, 'accuracy': 12},
    ));

    await tester.enterText(find.byKey(const Key('host')), 'acme.example.com');
    await tester.enterText(find.byKey(const Key('email')), 'a@b.test');
    await tester.enterText(find.byKey(const Key('pass')), 'wrong');
    await tester.tap(find.byKey(const Key('signin')));
    await tester.pumpAndSettle();
    expect(find.text('The supplied credentials are invalid.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('pass')), 'right');
    await tester.tap(find.byKey(const Key('signin')));
    await tester.pumpAndSettle();
    expect(find.text('Not checked in'), findsOneWidget);
    expect(find.text('7:48'), findsOneWidget);
    expect(prefs.getString('token'), 'tok123');

    await tester.tap(find.byKey(const Key('ci')));
    await tester.pumpAndSettle();
    expect(find.text('Checked in'), findsOneWidget);
    expect(server.calls, contains('POST /api/v1/attendance/check-in'));

    await tester.tap(find.byKey(const Key('lang')));
    await tester.pumpAndSettle();
    expect(find.text('حاضر'), findsOneWidget);

    await tester.tap(find.byKey(const Key('co')));
    await tester.pumpAndSettle();
    expect(find.text('سجلت انصراف'), findsOneWidget);
    expect(server.lastBody!['latitude'], 30.04);

    await tester.tap(find.byKey(const Key('logout')));
    await tester.pumpAndSettle();
    expect(prefs.getString('token'), isNull);
  });
}
