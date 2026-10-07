import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

/// Error with a message that is safe to show to the employee.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;

  /// Laravel validation errors: field → first message.
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

/// The device token was revoked / the login deactivated — the app returns to the login screen.
class UnauthorizedException extends ApiException {
  UnauthorizedException() : super('You have been signed out. Please sign in again.', statusCode: 401);
}

/// A file to upload with a multipart request.
class UploadFile {
  UploadFile(this.field, this.path);

  final String field;
  final String path;
}

/// Thin JSON client for the HRMS mobile API (`/api/mobile/...`).
class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  String baseUrl = '';
  String? token;

  /// Called once when any request comes back 401, so the app can sign out globally.
  void Function()? onUnauthorized;

  static const _timeout = Duration(seconds: 30);

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final uri = Uri.parse('$baseUrl/api/mobile$path');
    if (query == null || query.isEmpty) return uri;
    return uri.replace(queryParameters: query.map((k, v) => MapEntry(k, '$v')));
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => http.get(_uri(path, query), headers: _headers));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) => _send(() => http.post(
        _uri(path),
        headers: {..._headers, 'Content-Type': 'application/json'},
        body: jsonEncode(body ?? {}),
      ));

  /// Multipart POST for forms with attachments. Nested values (e.g. `lines`) are flattened to
  /// Laravel's `lines[0][field]` convention.
  Future<Map<String, dynamic>> postMultipart(String path, Map<String, dynamic> fields, List<UploadFile> files) {
    return _send(() async {
      final request = http.MultipartRequest('POST', _uri(path))..headers.addAll(_headers);
      _flatten(fields).forEach((k, v) => request.fields[k] = v);
      for (final f in files) {
        request.files.add(await http.MultipartFile.fromPath(f.field, f.path));
      }
      return http.Response.fromStream(await request.send());
    });
  }

  /// Downloads a file (payslip PDF, receipt, policy) into the app's documents folder.
  Future<File> download(String path, String fallbackName) async {
    final http.Response response;
    try {
      response = await http.get(_uri(path), headers: _headers).timeout(const Duration(minutes: 2));
    } on SocketException {
      throw ApiException('No internet connection.');
    } on TimeoutException {
      throw ApiException('The download timed out. Please try again.');
    }

    if (response.statusCode == 401) {
      onUnauthorized?.call();
      throw UnauthorizedException();
    }
    if (response.statusCode != 200) {
      throw ApiException(response.statusCode == 404 ? 'File not found.' : 'Download failed (${response.statusCode}).');
    }

    final disposition = response.headers['content-disposition'] ?? '';
    final match = RegExp(r'''filename\*?=(?:UTF-8'')?"?([^";]+)"?''', caseSensitive: false).firstMatch(disposition);
    final name = (match != null ? Uri.decodeComponent(match.group(1)!) : fallbackName).replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');

    final dir = Directory('${(await getApplicationDocumentsDirectory()).path}/downloads');
    await dir.create(recursive: true);
    return File('${dir.path}/$name').writeAsBytes(response.bodyBytes);
  }

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() call) async {
    final http.Response response;
    try {
      response = await call().timeout(_timeout);
    } on SocketException {
      throw ApiException('No internet connection, or the HRMS server cannot be reached.');
    } on TimeoutException {
      throw ApiException('The server took too long to respond. Please try again.');
    } on HttpException {
      throw ApiException('Could not reach the HRMS server.');
    }

    final body = _decode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) return body;

    if (response.statusCode == 401) {
      onUnauthorized?.call();
      throw UnauthorizedException();
    }

    final fieldErrors = <String, String>{};
    final errors = body['errors'];
    if (errors is Map) {
      errors.forEach((k, v) {
        fieldErrors['$k'] = v is List && v.isNotEmpty ? '${v.first}' : '$v';
      });
    }

    final message = fieldErrors.isNotEmpty
        ? fieldErrors.values.first
        : (body['message'] as String?)?.trim().isNotEmpty == true
            ? body['message'] as String
            : switch (response.statusCode) {
                403 => 'You are not allowed to do that.',
                404 => 'Not found.',
                429 => 'Too many attempts. Please wait a minute.',
                >= 500 => 'Something went wrong on the server. Please try again later.',
                _ => 'Request failed (${response.statusCode}).',
              };

    throw ApiException(message, statusCode: response.statusCode, fieldErrors: fieldErrors);
  }

  Map<String, dynamic> _decode(String body) {
    if (body.isEmpty) return {};
    try {
      final decoded = jsonDecode(body);
      return decoded is Map<String, dynamic> ? decoded : {'data': decoded};
    } catch (_) {
      return {};
    }
  }

  static Map<String, String> _flatten(Map<String, dynamic> data, [String prefix = '']) {
    final out = <String, String>{};
    data.forEach((key, value) {
      final name = prefix.isEmpty ? key : '$prefix[$key]';
      if (value == null) return;
      if (value is Map<String, dynamic>) {
        out.addAll(_flatten(value, name));
      } else if (value is List) {
        for (var i = 0; i < value.length; i++) {
          final item = value[i];
          if (item is Map<String, dynamic>) {
            out.addAll(_flatten(item, '$name[$i]'));
          } else if (item != null) {
            out['$name[$i]'] = '$item';
          }
        }
      } else if (value is bool) {
        out[name] = value ? '1' : '0';
      } else {
        out[name] = '$value';
      }
    });
    return out;
  }
}
