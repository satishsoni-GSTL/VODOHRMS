<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Joining details') · {{ config('app.name') }}</title>
    <style>
        :root {
            --brand: #0F7A78; --brand-dark: #0b5e5c; --brand-soft: #e7f4f3;
            --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; --bg: #f4f6f8; --card: #ffffff;
            --danger: #b42318; --danger-soft: #fef3f2; --ok: #067647; --ok-soft: #ecfdf3; --warn: #b54708; --warn-soft: #fffaeb;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; }
        a { color: var(--brand); }
        .topbar { background: var(--card); border-bottom: 1px solid var(--border); }
        .topbar-inner { max-width: 900px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--brand-dark); }
        .brand img { height: 32px; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px 16px 48px; }
        .narrow { max-width: 460px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
        .card + .card { margin-top: 20px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 17px; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
        .muted { color: var(--muted); font-size: 14px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 8px; padding: 10px 16px;
            background: var(--brand); color: #fff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
        .btn:hover { background: var(--brand-dark); }
        .btn-light { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .btn-light:hover { background: var(--bg); }
        .btn-link { background: none; border: 0; color: var(--brand); padding: 0; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-danger-link { color: var(--danger); }
        .btn-block { width: 100%; }
        input[type=text], input[type=email], input[type=tel], input[type=date], select, textarea {
            width: 100%; border: 1px solid #d0d5dd; border-radius: 8px; padding: 9px 12px; font: inherit; color: inherit; background: #fff; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--brand); outline-offset: 0; border-color: var(--brand); }
        input[readonly] { background: var(--bg); color: var(--muted); }
        .code-input { font-size: 26px; letter-spacing: 10px; text-align: center; font-variant-numeric: tabular-nums; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .req::after { content: ' *'; color: var(--danger); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 14px; }
        .full { grid-column: 1 / -1; }
        .alert { border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
        .alert-ok { background: var(--ok-soft); color: var(--ok); }
        .alert-error { background: var(--danger-soft); color: var(--danger); }
        .alert-warn { background: var(--warn-soft); color: var(--warn); }
        .field-error { color: var(--danger); font-size: 12px; margin-top: 3px; }
        .stack > * + * { margin-top: 14px; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .space-between { justify-content: space-between; }
        .progress { height: 10px; background: var(--border); border-radius: 999px; overflow: hidden; }
        .progress > span { display: block; height: 100%; background: var(--brand); }
        .pill { font-size: 12px; font-weight: 600; padding: 1px 9px; border-radius: 999px; background: var(--bg); color: var(--muted); }
        .pill-ok { background: var(--ok-soft); color: var(--ok); }
        .pill-warn { background: var(--warn-soft); color: var(--warn); }
        .doc { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: center; justify-content: space-between; padding: 12px 0; border-top: 1px solid var(--border); }
        .doc:first-of-type { border-top: 0; }
        .sticky-actions { position: sticky; bottom: 0; background: var(--bg); padding: 14px 0; display: flex; gap: 10px; justify-content: flex-end; }
        footer { text-align: center; color: var(--muted); font-size: 12px; padding: 0 16px 24px; }
        @media (max-width: 600px) { .card { padding: 18px; } .sticky-actions { flex-direction: column-reverse; } .sticky-actions .btn { width: 100%; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <span class="brand">
                @if (file_exists(public_path('images/globalspace-logo.png')))
                    <img src="/images/globalspace-logo.png" alt="">
                @endif
                <span>Joining Details</span>
            </span>
            @yield('topbar-actions')
        </div>
    </header>

    <main class="container @yield('container-class')">
        @yield('content')
    </main>

    <footer>Your details are sent securely to HR. Do not forward your invitation link.</footer>
</body>
</html>
