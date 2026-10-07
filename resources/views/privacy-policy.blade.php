<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy · VODO HRMS</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Figtree, Roboto, Helvetica, Arial, sans-serif;
            background: #f5f8f8;
            color: #1f2937;
            line-height: 1.6;
        }
        header {
            background: linear-gradient(135deg, #063a39 0%, #0c8481 55%, #13a39c 100%);
            color: #fff;
            padding: 2rem 1rem 1.75rem;
            text-align: center;
        }
        header img { height: 2.5rem; background: #fff; padding: .5rem .9rem; border-radius: .6rem; }
        header h1 { margin: 1rem 0 .25rem; font-size: 1.6rem; }
        header p { margin: 0; opacity: .85; font-size: .95rem; }
        .strip { height: 4px; background: linear-gradient(90deg, #f6322a, #f1892c, #eeae2d, #a7d253, #2aa4f1); }
        main { max-width: 46rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        section { background: #fff; border: 1px solid #e2eceb; border-radius: .9rem; padding: 1.25rem 1.4rem; margin-bottom: 1rem; }
        h2 { color: #0a6b69; font-size: 1.1rem; margin: 0 0 .5rem; }
        ul { padding-left: 1.2rem; margin: .4rem 0; }
        li { margin: .25rem 0; }
        a { color: #0c8481; }
        footer { text-align: center; color: #6b7280; font-size: .85rem; }
    </style>
</head>
<body>
<header>
    @if (file_exists(public_path('images/globalspace-logo.png')))
        <img src="{{ asset('images/globalspace-logo.png') }}" alt="GlobalSpace">
    @endif
    <h1>Privacy Policy</h1>
    <p>VODO HRMS — employee web portal and mobile app · Last updated {{ $updated }}</p>
</header>
<div class="strip"></div>

<main>
    <section>
        <h2>Who we are</h2>
        <p>
            VODO HRMS is the human-resources system of GlobalSpace (“we”, “the company”). It is
            used only by our employees, with accounts created by HR. The mobile app (“VODO HRMS”
            on Google Play) gives employees access to their own HR information. This policy
            explains what data the app and portal handle and why.
        </p>
    </section>

    <section>
        <h2>Information we process</h2>
        <ul>
            <li><strong>Account and profile:</strong> name, employee code, official e-mail, designation, department, reporting manager, date of joining, date of birth, contact details, and masked bank details, all as maintained by HR.</li>
            <li><strong>Attendance:</strong> check-in/out times from biometric devices, and Work From Home clock-ins made in the app.</li>
            <li><strong>Requests you submit:</strong> leave, Work From Home, attendance regularization, expense claims, loans/advances and resignations, including reasons and any attachments.</li>
            <li><strong>Photos and files:</strong> only the receipts or documents you choose to attach, using the camera or your gallery. The app does not access other photos or files.</li>
            <li><strong>Payroll:</strong> salary slips, earnings, deductions and loss-of-pay details.</li>
            <li><strong>Device and security data:</strong> device model, app version, IP address, sign-in times and a device sign-in token. These are used to keep your account secure and to let HR sign a lost phone out.</li>
            <li><strong>Notifications:</strong> a push-notification token issued by Google Firebase, used only to send you HR notifications.</li>
        </ul>
        <p>We do <strong>not</strong> collect your location, contacts, call logs or browsing history, and the app shows no advertising.</p>
    </section>

    <section>
        <h2>Why we use it</h2>
        <ul>
            <li>To run HR processes: attendance, leave, approvals, expenses, payroll and payslips.</li>
            <li>To notify you about approvals, request updates, payslips, holidays and company announcements.</li>
            <li>To show company-wide birthdays and work anniversaries. Only the day is shown, never your birth year or age.</li>
            <li>To keep accounts secure: sign-in records, device management, and fraud and abuse prevention.</li>
        </ul>
    </section>

    <section>
        <h2>Who can see it</h2>
        <ul>
            <li><strong>You</strong> can see your own data.</li>
            <li><strong>Your managers</strong> can see their team's attendance and requests, and act on approvals.</li>
            <li><strong>Authorised HR, payroll and finance staff</strong> can see the data they need for their role.</li>
            <li><strong>Service providers:</strong> our hosting provider, and Google Firebase Cloud Messaging for delivering notifications. They process data only on our instructions.</li>
        </ul>
        <p>We never sell personal data or share it for advertising.</p>
    </section>

    <section>
        <h2>Security</h2>
        <p>
            All app traffic is encrypted (HTTPS). Your sign-in token is stored in your phone's secure
            storage (Android Keystore or iOS Keychain), and the server keeps only a hashed copy. You
            can sign out at any time. HR can also sign out any of your devices, and access stops
            when your employee account is deactivated.
        </p>
    </section>

    <section>
        <h2>How long we keep it</h2>
        <p>
            We keep HR records for as long as you are employed and afterwards for the periods
            required by employment, tax and statutory law. Device sign-in records are removed when
            you sign out or when HR revokes the device.
        </p>
    </section>

    <section>
        <h2>Your choices and rights</h2>
        <ul>
            <li>You can see and correct your information through HR. Profile details are changed by HR.</li>
            <li>You can turn off notifications in your phone's settings, or sign out of the app at any time.</li>
            <li>Accounts are created and closed by HR, not in the app. To ask for access to, correction of or deletion of your data, contact HR at the address below. Some records must be kept by law.</li>
        </ul>
    </section>

    <section>
        <h2>Contact</h2>
        <p>
            Questions about this policy or your data:
            <a href="mailto:{{ $contact }}">{{ $contact }}</a>
        </p>
    </section>

    <footer>© {{ now()->year }} GlobalSpace · VODO HRMS</footer>
</main>
</body>
</html>
