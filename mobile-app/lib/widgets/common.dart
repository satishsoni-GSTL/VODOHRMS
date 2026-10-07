import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:open_filex/open_filex.dart';

import '../api/api_client.dart';
import '../utils/format.dart';

/// Loads data with [load], shows a spinner / error with retry, and supports pull-to-refresh.
/// Call [AsyncViewState.reload] (via a GlobalKey) after saving something.
class AsyncView<T> extends StatefulWidget {
  const AsyncView({super.key, required this.load, required this.builder});

  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;

  @override
  State<AsyncView<T>> createState() => AsyncViewState<T>();
}

class AsyncViewState<T> extends State<AsyncView<T>> {
  T? _data;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    setState(() {
      _loading = _data == null;
      _error = null;
    });
    try {
      final data = await widget.load();
      if (mounted) setState(() => _data = data);
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) return const Center(child: CircularProgressIndicator());

    if (_error != null && _data == null) {
      return ErrorState(message: _error is ApiException ? (_error as ApiException).message : '$_error', onRetry: reload);
    }

    return RefreshIndicator(
      onRefresh: reload,
      child: widget.builder(context, _data as T, reload),
    );
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.cloud_off_outlined, size: 48, color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.tonalIcon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Try again')),
          ]),
        ),
      );
}

/// Scrollable empty state (so pull-to-refresh still works on an empty list).
class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 120),
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.outline),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
        ],
      );
}

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, required this.label});

  final String? status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = statusColor(status, Theme.of(context).colorScheme);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});

  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
        child: Row(children: [
          Expanded(child: Text(text, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700))),
          ?trailing,
        ]),
      );
}

class InfoRow extends StatelessWidget {
  const InfoRow(this.label, this.value, {super.key});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(
            width: 130,
            child: Text(label, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ),
          Expanded(child: Text(value == null || value!.isEmpty ? '—' : value!)),
        ]),
      );
}

/// Approver remarks (rejection / send-back reason) shown on a request card.
class RemarksBox extends StatelessWidget {
  const RemarksBox(this.remarks, {super.key});

  final String remarks;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(top: 8),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text('Approver: $remarks', style: Theme.of(context).textTheme.bodySmall),
      );
}

void showSnack(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? Theme.of(context).colorScheme.error : null,
    ));
}

String errorText(Object e) => e is ApiException ? e.message : 'Something went wrong. Please try again.';

/// Asks for (optionally required) remarks; returns null if cancelled.
Future<String?> askRemarks(BuildContext context, {required String title, required String label, bool required = true, String confirm = 'Submit'}) {
  final controller = TextEditingController();
  final formKey = GlobalKey<FormState>();

  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Form(
        key: formKey,
        child: TextFormField(
          controller: controller,
          autofocus: true,
          minLines: 2,
          maxLines: 5,
          decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
          validator: (v) => required && (v == null || v.trim().isEmpty) ? 'Required' : null,
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
        FilledButton(
          onPressed: () {
            if (formKey.currentState!.validate()) Navigator.pop(ctx, controller.text.trim());
          },
          child: Text(confirm),
        ),
      ],
    ),
  );
}

Future<bool> confirmDialog(BuildContext context, String title, String message, {String confirm = 'Confirm'}) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(confirm)),
        ],
      ),
    ) ??
    false;

/// Downloads a file from the API and opens it in the phone's viewer.
Future<void> downloadAndOpen(BuildContext context, String apiPath, String fallbackName) async {
  showSnack(context, 'Downloading…');
  try {
    final file = await ApiClient.instance.download(apiPath, fallbackName);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    final result = await OpenFilex.open(file.path);
    if (result.type != ResultType.done && context.mounted) {
      showSnack(context, 'Saved, but no app on this phone can open this file type.');
    }
  } catch (e) {
    if (context.mounted) showSnack(context, errorText(e), error: true);
  }
}

/// Lets the employee attach a photo (camera / gallery) or a PDF. Shows the chosen file name.
class AttachmentField extends StatelessWidget {
  const AttachmentField({super.key, required this.path, required this.onChanged, this.label = 'Attachment', this.required = false});

  final String? path;
  final ValueChanged<String?> onChanged;
  final String label;
  final bool required;

  Future<void> _pick(BuildContext context) async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('Take photo'), onTap: () => Navigator.pop(ctx, 'camera')),
          ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('Choose from gallery'), onTap: () => Navigator.pop(ctx, 'gallery')),
          ListTile(leading: const Icon(Icons.picture_as_pdf_outlined), title: const Text('Choose PDF'), onTap: () => Navigator.pop(ctx, 'pdf')),
        ]),
      ),
    );

    String? picked;
    try {
      if (choice == 'camera' || choice == 'gallery') {
        final image = await ImagePicker().pickImage(
          source: choice == 'camera' ? ImageSource.camera : ImageSource.gallery,
          imageQuality: 70,
          maxWidth: 1800,
        );
        picked = image?.path;
      } else if (choice == 'pdf') {
        final file = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: ['pdf']);
        picked = file?.path;
      }
    } catch (_) {
      if (context.mounted) showSnack(context, 'Could not open the camera or files.', error: true);
    }

    if (picked != null) {
      if (await File(picked).length() > 10 * 1024 * 1024) {
        if (context.mounted) showSnack(context, 'File is larger than 10 MB.', error: true);
        return;
      }
      onChanged(picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = path?.split(RegExp(r'[\\/]')).last;
    return InputDecorator(
      decoration: InputDecoration(labelText: required ? '$label *' : label, border: const OutlineInputBorder()),
      child: Row(children: [
        Icon(name == null ? Icons.attach_file : Icons.insert_drive_file_outlined, size: 20),
        const SizedBox(width: 8),
        Expanded(child: Text(name ?? 'None', overflow: TextOverflow.ellipsis)),
        if (name != null) IconButton(icon: const Icon(Icons.close), tooltip: 'Remove', onPressed: () => onChanged(null)),
        TextButton(onPressed: () => _pick(context), child: Text(name == null ? 'Add' : 'Change')),
      ]),
    );
  }
}

/// Date field that opens a date picker.
class DateField extends StatelessWidget {
  const DateField({super.key, required this.label, required this.value, required this.onChanged, this.first, this.last});

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final DateTime? first;
  final DateTime? last;

  @override
  Widget build(BuildContext context) => FormField<DateTime>(
        initialValue: value,
        validator: (_) => value == null ? 'Required' : null,
        builder: (state) => InkWell(
          onTap: () async {
            final now = DateTime.now();
            final picked = await showDatePicker(
              context: context,
              initialDate: value ?? (last != null && last!.isBefore(now) ? last! : (first != null && first!.isAfter(now) ? first! : now)),
              firstDate: first ?? DateTime(now.year - 2),
              lastDate: last ?? DateTime(now.year + 2),
            );
            if (picked != null) {
              onChanged(picked);
              state.didChange(picked);
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: label,
              border: const OutlineInputBorder(),
              suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
              errorText: state.errorText,
            ),
            child: Text(value == null ? 'Select' : dateLabel(ymd(value!))),
          ),
        ),
      );
}
