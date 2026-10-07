# VODO HRMS — employee mobile app

A native Flutter app (Android and iOS) for employee self-service and manager approvals.
Employees sign in **once** per phone. The app talks to the HRMS through a JSON API
(`/api/mobile/...`). The API calls the same services and approval workflow as the web
panel, so leave balances, LOP, WFH rules and approvals behave exactly as on the website.

## Features

| Tab / screen | What employees can do |
|---|---|
| **Home** | See today's in/out times and hours, clock in and out on an approved Work From Home day, check leave balances, open requests and upcoming holidays, and use quick actions. Approvers see how many requests are waiting for them. |
| **Attendance** | Browse attendance month by month with colour-coded days (P / WFH / HD / L / A / MP / H / WO) and totals. Tap a day to request a regularization for it. |
| **Requests** | Use the Leave, WFH and Regularization tabs: see each request's status and the approver's remarks, and **Reapply** to any rejected or sent-back request with the old details pre-filled. |
| Apply leave | Pick a leave type with its live balance and a full or half day. The form shows how many working days the dates use (weekly offs and holidays are not counted). Add an attachment from the camera, gallery or a PDF. |
| **Team** (managers and approvers) | **Approvals:** approve, send back or reject leave, WFH, regularization, expense, loan and resignation requests (rejecting or sending back requires remarks). **Today:** who is in the office, on WFH, on leave, absent or not in yet, for any date, with in/out times; tap a person to see their attendance month. **Leave:** who is away each month. **Requests:** the team's WFH, regularization and expense requests. This covers direct and indirect reports. |
| Expenses | Claims with several line items, a receipt photo or PDF for each, a running total, and the approval history. **Correct & resubmit** a sent-back claim; receipts already attached are kept. |
| Salary slips | Earnings and deductions breakdown, gross, net (in-hand) and LOP, plus the PDF download. |
| Holidays | The year's holiday calendar. **Claim** an optional holiday (within the yearly limit) or cancel a claim. |
| Loans | Request a loan or salary advance, and track its status, monthly recovery and outstanding balance. |
| HR policies | Download policy documents. |
| Profile / password | Employment and personal details (the bank account number is masked), and changing the password. Employees who signed in with a temporary password are asked to change it straight away. |

## One-time sign-in

1. The employee signs in with their employee code (or email) and HRMS password. The same
   rules as the web login apply: the account must be active and linked to an employee
   record, and every attempt is recorded in the login audit.
2. The server issues a device token. The app keeps it in secure storage (Android Keystore
   or iOS Keychain); the server stores only its SHA-256 hash.
3. Every API call sends the token. The employee stays signed in until one of these happens:
   - they choose **More → Sign out**;
   - HR signs the phone out under **Roles & Permissions → Mobile App Devices**;
   - their login is deactivated.

   The app then returns to the login screen on its own.

## Push notifications (Firebase)

Phones get a push notification for:
- a new request waiting for approval;
- a request approved, rejected or sent back (with the approver's remarks);
- a payslip that is ready;
- announcements, upcoming holidays, birthdays and work anniversaries;
- exit clearance and full & final settlement updates.

Tapping a notification opens the matching screen. Push runs alongside the existing e-mails
and does not replace them.

Push is off until both sides are configured:

1. **Create the Firebase project:**
   - Create a project in the [Firebase console](https://console.firebase.google.com).
   - Add an **Android** app with package `com.globalspace.vodo_hrms`.
   - Add an **iOS** app if you need one.
2. **Configure the server:** go to Project settings → Service accounts → *Generate new private
   key*. Save the JSON file **outside the web root** and add this to the HRMS `.env`:
   ```
   FIREBASE_CREDENTIALS=/path/to/firebase-service-account.json
   ```
3. **Configure the app:** in Project settings → General → Your apps, copy the values into
   the build command. No `google-services.json` is needed:
   ```bash
   flutter build apk --release \
     --dart-define=HRMS_URL=https://hrms.yourcompany.com \
     --dart-define=FIREBASE_API_KEY=AIza... \
     --dart-define=FIREBASE_PROJECT_ID=your-project \
     --dart-define=FIREBASE_SENDER_ID=1234567890 \
     --dart-define=FIREBASE_ANDROID_APP_ID=1:1234567890:android:abc123 \
     --dart-define=FIREBASE_IOS_APP_ID=1:1234567890:ios:def456
   ```
4. **iOS only:** in Xcode, enable the *Push Notifications* and *Background Modes → Remote
   notifications* capabilities, then upload an APNs key in Firebase → Cloud Messaging.

The app registers its push token after sign-in. Signing out, or HR revoking the device,
stops pushes to that phone. Tokens Firebase reports as dead are removed automatically.

## Look and feel

The app uses the GlobalSpace brand colours, taken from the logo:

| Use | Colour |
|---|---|
| Primary | teal `#0C8481` |
| Accent | orange `#F1892C` |
| Headers and logo strip | the logo's red → orange → yellow → green → blue gradient |

It also has:
- the logo on the sign-in screen and the "G" mark on the home screen;
- the "G" mark as the app icon;
- a matching dark mode.

To regenerate the icons after changing `assets/images/app_icon*.png`, run
`dart run flutter_launcher_icons`.

## Run and build

```bash
cd mobile-app
flutter pub get

# Real phone on the same Wi-Fi as the PC running the HRMS:
#   php artisan serve --host=0.0.0.0 --port=8000
flutter run --dart-define=HRMS_URL=http://192.168.1.8:8000

# Android emulator
flutter run --dart-define=HRMS_URL=http://10.0.2.2:8000

# Release builds (use your public https address)
flutter build apk --release --dart-define=HRMS_URL=https://hrms.yourcompany.com --dart-define=ALLOW_SERVER_CHANGE=false
flutter build ipa  --release --dart-define=HRMS_URL=https://hrms.yourcompany.com --dart-define=ALLOW_SERVER_CHANGE=false
```

`ALLOW_SERVER_CHANGE=false` hides the *Server address* field on the login screen.

## Production / Play Store

The full step-by-step guide is in **[PLAY_STORE.md](PLAY_STORE.md)**: signing key backup,
server checklist, store listing text, Data safety answers and release. In short:

```powershell
copy release-config.example.json release-config.json   # set HRMS_URL (https) and Firebase keys
powershell -ExecutionPolicy Bypass -File build-release.ps1
# → build\app\outputs\bundle\release\app-release.aab  (upload to Play Console)
```

Production setup already in place:

**Security and build**
- Release signing uses the upload key in `android/upload-keystore.jks` and
  `android/key.properties`. Both are git-ignored, so **back them up**.
- Release builds are HTTPS-only. Plain http is allowed only in debug and profile builds,
  and on iOS only for local-network hosts.
- R8 code shrinking and resource shrinking are on, Dart code is obfuscated, and debug
  symbols go to `build/symbols`.

**Branding and listing**
- The app icon is the GlobalSpace "G" mark, with an adaptive icon on Android.
- The splash screen uses the brand colours, in light and dark.
- The status-bar notification icon is a white "G" silhouette.
- The in-app *More* menu links to the **Privacy policy** (`/privacy-policy` on the HRMS
  website) and has an **About** entry showing the version.
- Store graphics are in `store/`: the 512×512 icon and the 1024×500 feature graphic.

## Before going live

- **HTTPS:** serve the HRMS over HTTPS. Plain http is allowed only for local testing, via
  `usesCleartextTraffic` in the Android manifest and `NSAllowsArbitraryLoads` in the iOS
  Info.plist. Remove both for production.
- **Signing:**
  - Android: create a release keystore and configure it in `android/app/build.gradle.kts`.
  - iOS: set the team and bundle ID in Xcode.
- **App icon:** replace the default icon, for example with `flutter_launcher_icons`.
- **Workflows:** set up an approval workflow for every module employees will use (leave,
  WFH, regularization, expense, loan). Without one, the app shows "no approval workflow
  configured" for that request type.

## Code map

```
lib/
  main.dart                    app start; sign-in vs. app switch (reacts to sign-out anywhere)
  config.dart                  server URL / build flags
  api/api_client.dart          JSON + multipart + downloads, validation errors, 401 → sign out
  services/auth_service.dart   one-time login, secure token storage, cached user
  utils/format.dart            ₹ / date formatting, status colours
  widgets/common.dart          loading/error/retry, pull-to-refresh, chips, attachment & date pickers
  screens/                     home_shell (bottom nav), dashboard, attendance, requests, leave/wfh/
                               regularization forms, approvals, expenses (+form), payslips, holidays,
                               loans, policies, profile, change_password, more, login
```

## Backend

- Routes: `routes/api.php` (prefix `mobile`).
- Token check: `app/Http/Middleware/AuthenticateMobileDevice.php`.
- Login and token service: `app/Services/MobileAuthService.php`.
- Controllers: `app/Http/Controllers/Api/Mobile/*`.
- Tests: `tests/Feature/MobileAppAuthTest.php` and `MobileAppApiTest.php`.
