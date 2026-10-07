import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

final _money = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);
final _moneyShort = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 0);

String money(num? v, {bool short = false}) => v == null ? '—' : (short ? _moneyShort : _money).format(v);

DateTime? parseDate(String? s) => s == null || s.isEmpty ? null : DateTime.tryParse(s);

/// 07 Oct 2026
String dateLabel(String? s) {
  final d = parseDate(s);
  return d == null ? '—' : DateFormat('dd MMM yyyy').format(d);
}

/// Tue, 07 Oct
String dayLabel(String? s) {
  final d = parseDate(s);
  return d == null ? '—' : DateFormat('EEE, dd MMM').format(d);
}

String dateRange(String? from, String? to) {
  if (from == null) return '—';
  if (to == null || to == from) return dateLabel(from);
  return '${dateLabel(from)} → ${dateLabel(to)}';
}

/// "2026-09" → "September 2026"
String monthLabel(String? ym) {
  final d = ym == null ? null : DateTime.tryParse('$ym-01');
  return d == null ? (ym ?? '—') : DateFormat('MMMM yyyy').format(d);
}

String ymd(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

/// "09:30:00" → "09:30"
String timeLabel(String? t) => t == null || t.isEmpty ? '—' : (t.length >= 5 ? t.substring(0, 5) : t);

String daysLabel(num? days) {
  if (days == null) return '—';
  final s = days == days.roundToDouble() ? days.toInt().toString() : days.toString();
  return '$s day${days == 1 ? '' : 's'}';
}

String num2(num? v) => v == null ? '—' : (v == v.roundToDouble() ? v.toInt().toString() : v.toStringAsFixed(2));

/// Colour for a request status chip.
Color statusColor(String? status, ColorScheme scheme) => switch (status) {
      'approved' || 'paid' || 'active' || 'closed' || 'hr_approved' => const Color(0xFF16A34A),
      'rejected' || 'cancelled' => scheme.error,
      'sent_back' => const Color(0xFFE07A1F),
      _ => const Color(0xFF1E88C8),
    };

/// Attendance day codes from the API → colour (same palette idea as the web register).
Color attendanceColor(String code) => switch (code) {
      'P' => const Color(0xFF16A34A),
      'HD' => const Color(0xFFF59E0B),
      'WFH' => const Color(0xFF0EA5E9),
      'OD' => const Color(0xFF6366F1),
      'L' => const Color(0xFF8B5CF6),
      'H' => const Color(0xFFEC4899),
      'WO' => const Color(0xFF94A3B8),
      'A' => const Color(0xFFDC2626),
      'MP' => const Color(0xFFEA580C),
      _ => const Color(0xFFCBD5E1),
    };
