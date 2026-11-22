import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiException implements Exception {
  ApiException(this.message, {this.unauthorized = false});
  final String message;
  final bool unauthorized;
  @override
  String toString() => message;
}

/// Thin client for the Afrad attendance token API (/api/v1). One instance per company address.
class AfradApi {
  AfradApi(this.host, {this.token, http.Client? client, String? scheme}) : scheme = scheme ?? (host.split(':').first.endsWith('localhost') ? 'http' : 'https'), _client = client ?? http.Client();

  final String host;
  final String scheme;
  String? token;
  final http.Client _client;

  /// Accepts "acme.example.com", "https://acme.example.com/" and strips paths.
  static String cleanHost(String input) {
    var h = input.trim().toLowerCase();
    h = h.replaceFirst(RegExp(r'^https?://'), '');
    final slash = h.indexOf('/');
    if (slash >= 0) h = h.substring(0, slash);
    return h;
  }

  Uri _uri(String path, [Map<String, String>? q]) => Uri(scheme: scheme, host: host.split(':').first, port: host.contains(':') ? int.tryParse(host.split(':').last) : null, path: '/api/v1$path', queryParameters: q);

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() call) async {
    http.Response r;
    try {
      r = await call();
    } catch (_) {
      throw ApiException('offline');
    }
    Map<String, dynamic> body = {};
    try {
      final decoded = jsonDecode(r.body);
      if (decoded is Map<String, dynamic>) body = decoded;
    } catch (_) {}
    if (r.statusCode == 401) throw ApiException('unauthorized', unauthorized: true);
    if (r.statusCode >= 400) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        throw ApiException(first is List ? first.first.toString() : first.toString());
      }
      throw ApiException((body['message'] ?? 'failed').toString());
    }
    return body;
  }

  Future<String> login(String email, String password, String device) async {
    final b = await _send(() => _client.post(_uri('/auth/login'), headers: _headers, body: jsonEncode({'email': email, 'password': password, 'device_name': device})));
    final t = (b['data'] as Map)['token'] as String;
    token = t;
    return t;
  }

  Future<void> logout() async {
    try {
      await _send(() => _client.post(_uri('/auth/logout'), headers: _headers));
    } catch (_) {}
    token = null;
  }

  Future<Map<String, dynamic>> status() async => ((await _send(() => _client.get(_uri('/attendance/status'), headers: _headers)))['data'] as Map).cast<String, dynamic>();

  Future<List<Map<String, dynamic>>> history() async {
    final b = await _send(() => _client.get(_uri('/attendance/history', {'per_page': '7'}), headers: _headers));
    return ((b['data'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  Future<void> _record(String kind, Map<String, dynamic> loc) async {
    await _send(() => _client.post(_uri('/attendance/$kind'), headers: _headers, body: jsonEncode({...loc, 'client_timestamp': DateTime.now().toUtc().toIso8601String()})));
  }

  Future<void> checkIn(Map<String, dynamic> loc) => _record('check-in', loc);
  Future<void> checkOut(Map<String, dynamic> loc) => _record('check-out', loc);

  Future<void> breakStart(int sessionId) async => _send(() => _client.post(_uri('/attendance/breaks/start'), headers: _headers, body: jsonEncode({'attendance_session_id': sessionId})));
  Future<void> breakEnd(int sessionId) async => _send(() => _client.post(_uri('/attendance/breaks/end'), headers: _headers, body: jsonEncode({'attendance_session_id': sessionId})));
}
