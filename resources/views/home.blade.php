<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'VODOHRMS') }}</title>
    <meta http-equiv="refresh" content="2;url={{ $loginUrl }}">
    <style>
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Figtree, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #063a39 0%, #0f7a78 55%, #13a39c 100%);
            color: #1f2937;
        }
        .wrap {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
            padding: 2.75rem 2.25rem;
            text-align: center;
        }
        .logo-img {
            height: 3rem;
            margin: 0 auto 1.25rem;
            display: block;
        }
        .logo-fallback {
            width: fit-content;
            margin: 0 auto 1.25rem;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .logo-fallback .g1 { color: #f5a623; }
        .logo-fallback .g2 { color: #0f7a78; }
        h1 {
            margin: 0 0 0.4rem;
            font-size: 1.4rem;
            font-weight: 700;
            color: #111827;
        }
        p.tagline {
            margin: 0 0 1.75rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .spinner {
            width: 1.5rem;
            height: 1.5rem;
            margin: 0 auto 0.85rem;
            border: 3px solid #bfe9e6;
            border-top-color: #0f7a78;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .redirect-note {
            font-size: 0.85rem;
            color: #9ca3af;
            margin: 0 0 1.5rem;
        }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.7rem 1rem;
            border-radius: 0.6rem;
            background: #0f7a78;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: background 0.15s ease;
        }
        .btn:hover { background: #0b5f5d; }
        footer {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div>
            <div class="card">
                @if ($logoExists)
                    <img class="logo-img" src="{{ asset('images/globalspace-logo.png') }}" alt="{{ config('app.name', 'VODOHRMS') }}">
                @else
                    <div class="logo-fallback"><span class="g1">Global</span><span class="g2">Space</span></div>
                @endif
                <h1>HRMS</h1>
                <p class="tagline">Human Resource Management System</p>

                <div class="spinner" role="status" aria-label="Redirecting"></div>
                <p class="redirect-note">Redirecting you to the login page&hellip;</p>

                <a class="btn" href="{{ $loginUrl }}">Continue to Login</a>
            </div>
            <footer>&copy; {{ date('Y') }} GlobalSpace. All rights reserved.</footer>
        </div>
    </div>

    <script>
        setTimeout(function () {
            window.location.replace(@json($loginUrl));
        }, 1200);
    </script>
</body>
</html>
