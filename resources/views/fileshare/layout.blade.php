<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Shared File') · GlobalSpace Files</title>
    <style>
        :root {
            --brand: #0F7A78; --brand-dark: #0b5e5c; --brand-soft: #e7f4f3;
            --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; --bg: #f4f6f8; --card: #ffffff;
            --danger: #b42318; --danger-soft: #fef3f2; --ok: #067647; --ok-soft: #ecfdf3;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; }
        a { color: var(--brand); }
        .topbar { background: var(--card); border-bottom: 1px solid var(--border); }
        .topbar-inner { max-width: 1100px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--brand-dark); text-decoration: none; }
        .brand img { height: 32px; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        .narrow { max-width: 460px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
        .card + .card { margin-top: 20px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 17px; margin: 0 0 16px; }
        .muted { color: var(--muted); font-size: 14px; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 8px; padding: 10px 16px;
            background: var(--brand); color: #fff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
        .btn:hover { background: var(--brand-dark); }
        .btn-light { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .btn-light:hover { background: var(--bg); }
        .btn-link { background: none; border: 0; color: var(--brand); padding: 0; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-block { width: 100%; }
        input[type=text], textarea { width: 100%; border: 1px solid #d0d5dd; border-radius: 8px; padding: 10px 12px; font: inherit; color: inherit; }
        input:focus, textarea:focus { outline: 2px solid var(--brand); outline-offset: 0; border-color: var(--brand); }
        .code-input { font-size: 26px; letter-spacing: 10px; text-align: center; font-variant-numeric: tabular-nums; }
        label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .alert { border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
        .alert-ok { background: var(--ok-soft); color: var(--ok); }
        .alert-error { background: var(--danger-soft); color: var(--danger); }
        .stack > * + * { margin-top: 14px; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .space-between { justify-content: space-between; }
        .preview { margin-top: 20px; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: #fafafa; }
        .preview iframe { width: 100%; height: 75vh; border: 0; display: block; background: #fff; }
        .preview img, .preview video { max-width: 100%; display: block; margin: 0 auto; }
        .preview audio { width: 100%; display: block; padding: 16px; }
        .no-preview { padding: 40px 16px; text-align: center; color: var(--muted); }
        .comment { display: flex; gap: 12px; }
        .avatar { flex: none; width: 36px; height: 36px; border-radius: 50%; background: var(--brand-soft); color: var(--brand-dark);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
        .comment-body { flex: 1; min-width: 0; }
        .comment-head { display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: baseline; }
        .comment-text { white-space: pre-wrap; word-wrap: break-word; margin: 4px 0 6px; }
        .badge { font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; background: var(--brand-soft); color: var(--brand-dark); }
        .thread { padding: 16px 0; border-top: 1px solid var(--border); }
        .thread:first-of-type { border-top: 0; padding-top: 0; }
        .replies { margin: 12px 0 0 48px; display: grid; gap: 12px; }
        .reply-form { margin: 10px 0 0 48px; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        footer { text-align: center; color: var(--muted); font-size: 12px; padding: 0 16px 24px; }
        @media (max-width: 600px) {
            .card { padding: 18px; }
            .replies, .reply-form { margin-left: 20px; }
            .preview iframe { height: 60vh; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <span class="brand">
                @if (file_exists(public_path('images/globalspace-logo.png')))
                    <img src="/images/globalspace-logo.png" alt="GlobalSpace">
                @endif
                <span>Secure File Sharing</span>
            </span>
            @yield('topbar-actions')
        </div>
    </header>

    <main class="container @yield('container-class')">
        @yield('content')
    </main>

    <footer>Shared securely via GlobalSpace HRMS. Do not forward this link — access requires a code sent to your email.</footer>
</body>
</html>
