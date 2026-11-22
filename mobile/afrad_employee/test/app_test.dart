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

  testWidgets('request overtime and a correction from a day, and see their status', (tester) async {
    SharedPreferences.setMockInitialValues({'host': 'acme.example.com', 'token': 'tok123'});
    final prefs = await SharedPreferences.getInstance();
    final posts = <String, Map<String, dynamic>>{};
    final overtime = <Map<String, dynamic>>[];
    final client = MockClient((req) async {
      final path = req.url.path;
      if (req.method == 'POST') {
        final body = jsonDecode(req.body) as Map<String, dynamic>;
        posts[path] = body;
        if (path.endsWith('/overtime-requests')) overtime.add({'requested_minutes': body['requested_minutes'], 'status': 'pending'});
        return http.Response('{}', 201);
      }
      if (path.endsWith('/status')) return http.Response(jsonEncode({'data': {'date': '2026-11-22', 'state': 'checked_out', 'active_session': null}}), 200);
      if (path.endsWith('/history')) {
        return http.Response(jsonEncode({'data': [{'id': 5, 'attendance_date': '2026-11-21T00:00:00Z', 'total_worked_minutes': 468, 'late_minutes': 0, 'sessions': [{'id': 9}]}]}), 200);
      }
      if (path.endsWith('/overtime-requests')) return http.Response(jsonEncode({'data': {'data': overtime}}), 200);
      if (path.endsWith('/corrections')) return http.Response(jsonEncode({'data': {'data': []}}), 200);
      return http.Response('{}', 200);
    });
    await tester.pumpWidget(AfradApp(
      prefs: prefs,
      apiFactory: (h, t) => AfradApi(h, token: t, client: client),
      location: () async => {},
    ));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('noreq')), findsOneWidget);

    await tester.tap(find.byKey(const Key('day-5')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('act-ot')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('send-req')));
    await tester.pumpAndSettle();
    expect(find.text('Enter minutes from 1 to 1440.'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('ot-min')), '45');
    await tester.enterText(find.byKey(const Key('reason')), 'Stayed for the delivery');
    await tester.tap(find.byKey(const Key('send-req')));
    await tester.pumpAndSettle();
    expect(posts['/api/v1/attendance/overtime-requests']!['attendance_session_id'], 9);
    expect(posts['/api/v1/attendance/overtime-requests']!['requested_minutes'], 45);
    expect(find.text('45 min'), findsOneWidget);
    expect(find.text('Pending'), findsOneWidget);

    await tester.tap(find.byKey(const Key('day-5')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('act-corr')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('send-req')));
    await tester.pumpAndSettle();
    expect(find.text('Enter a reason.'), findsOneWidget);
    expect(posts.containsKey('/api/v1/attendance/corrections'), isFalse);
  });

  testWidgets('leave balances show and a pending request can be withdrawn', (tester) async {
    SharedPreferences.setMockInitialValues({'host': 'acme.example.com', 'token': 'tok123'});
    final prefs = await SharedPreferences.getInstance();
    final calls = <String>[];
    var status = 'pending';
    final client = MockClient((req) async {
      final path = req.url.path;
      calls.add('${req.method} $path');
      if (path.endsWith('/leave/4/cancel')) { status = 'cancelled'; return http.Response('{}', 200); }
      if (path.endsWith('/leave')) {
        return http.Response(jsonEncode({'data': {'balances': [{'leave_type_id': 1, 'name': 'Annual', 'remaining': 19.5}], 'requests': [{'id': 4, 'start_date': '2026-11-23', 'end_date': '2026-11-24', 'days': 2, 'status': status}]}}), 200);
      }
      if (path.endsWith('/status')) return http.Response(jsonEncode({'data': {'date': '2026-11-22', 'state': 'checked_out', 'active_session': null}}), 200);
      if (path.endsWith('/history')) return http.Response(jsonEncode({'data': []}), 200);
      return http.Response(jsonEncode({'data': {'data': []}}), 200);
    });
    await tester.pumpWidget(AfradApp(prefs: prefs, apiFactory: (h, t) => AfradApi(h, token: t, client: client), location: () async => {}));
    await tester.pumpAndSettle();
    expect(find.text('19.5 left'), findsOneWidget);
    expect(find.textContaining('2026-11-23'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('wd-4')));
    await tester.tap(find.byKey(const Key('wd-4')));
    await tester.pumpAndSettle();
    expect(calls, contains('POST /api/v1/leave/4/cancel'));
    expect(find.text('Withdrawn'), findsOneWidget);
  });
}
