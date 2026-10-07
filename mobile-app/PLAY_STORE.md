# Publishing VODO HRMS on Google Play

Package name: **`com.globalspace.vodo_hrms`** (permanent). App name: **VODO HRMS**.

---

## 0. Back up the signing key (do this first)

Release builds are signed with the **upload key** created on 7 Oct 2026:

| File | What it is |
|---|---|
| `android/upload-keystore.jks` | The key (alias `upload`) |
| `android/key.properties` | Its password and path |

Both files are git-ignored. **Copy both to a safe place**, such as a password manager or the
company vault. Every future update must be signed with this key. With Play App Signing
(the default) you can ask Google to reset a lost upload key, but that takes days and
blocks releases.

---

## 1. Get the server ready

The app talks only to the HRMS over **HTTPS**; release builds refuse plain `http`.

- Serve the HRMS on HTTPS, e.g. `https://hrms.yourcompany.com`.
- Set these in the production `.env`:
  ```
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://hrms.yourcompany.com
  PRIVACY_CONTACT_EMAIL=hr@yourcompany.com      # shown on /privacy-policy
  FIREBASE_CREDENTIALS=/secure/path/firebase-service-account.json   # optional: push
  ```
- Then run:
  ```
  php artisan migrate --force
  php artisan config:cache
  php artisan route:cache
  ```
- Check that `https://hrms.yourcompany.com/privacy-policy` opens. Play Store needs this URL.
- Make sure every module employees use has an active approval workflow: leave, WFH,
  regularization, expense and loan.

---

## 2. Build the release

1. Copy `release-config.example.json` to `release-config.json` (git-ignored) and set the
   `HRMS_URL` and, if you use push, the `FIREBASE_*` values. Setting up Firebase is
   covered in README.md.
2. Set the version in `pubspec.yaml`, e.g. `version: 1.0.0+1`. For every later upload,
   raise the number after the `+` (`1.0.1+2`, `1.0.2+3`…).
3. Run:
   ```powershell
   cd mobile-app
   powershell -ExecutionPolicy Bypass -File build-release.ps1
   ```
   This produces:

   | Output | Purpose |
   |---|---|
   | `build\app\outputs\bundle\release\app-release.aab` | Upload this to Play Console |
   | `build\app\outputs\flutter-apk\app-release.apk` | Install directly on test phones |
   | `build\symbols\` | Needed to read crash reports — keep a copy per version |

---

## 3. Play Console account

- Create a developer account at <https://play.google.com/console> (one-time US$25).
- Register it as an **Organization** account under GlobalSpace. This needs a D-U-N-S
  number.
  - Organization accounts skip the rule that new *personal* accounts must run a closed
    test with at least 12 testers for 14 days before going to production.

### Who should be able to install it?

The app is only useful to employees, because accounts are created by HR. Pick one option:

| Option | How it works | When to use |
|---|---|---|
| **A. Public listing** (simplest) | Anyone can find and install it, but only employees can sign in. | Default choice |
| **B. Private app via Managed Google Play** | Only your organisation sees it. | You manage phones with Google Workspace or an EMM/MDM |
| **C. Closed testing track only** | Only listed Google accounts can install it. | Fine for a pilot |

---

## 4. Create the app in Play Console

**Create app** with these answers:

| Field | Answer |
|---|---|
| App name | VODO HRMS |
| Default language | English (India) – en-IN |
| App or game | App |
| Free or paid | Free |
| Declarations | Accept |

### Store listing (Grow → Store presence → Main store listing)

**Short description** (80 characters maximum):
> Attendance, leave, WFH, expenses, payslips and approvals for GlobalSpace staff.

**Full description:**
> VODO HRMS is the official HR app for GlobalSpace employees. Sign in once with your employee code and manage your work life from your phone:
>
> • Attendance – see your monthly attendance, in/out times and hours; request a regularization for a missed or wrong punch.
> • Leave – check balances and apply for full or half-day leave with attachments.
> • Work From Home – request WFH and clock in/out on approved days.
> • Expenses – submit claims with receipt photos and track approval and payment.
> • Payslips – view earnings, deductions and net pay, and download the PDF.
> • Holidays – the holiday calendar; claim optional holidays.
> • Loans & salary advances – request and track recovery.
> • For managers – approve, reject or send back requests, and see your team's attendance, leave and requests.
> • Celebrations – company-wide birthdays and work anniversaries.
> • Notifications – instant updates on approvals, payslips and announcements.
>
> An account is provided by GlobalSpace HR. The app is for GlobalSpace employees only.

**Graphics** are in `mobile-app/store/`:

| Asset | File / requirement |
|---|---|
| App icon | `play-icon-512.png` (512×512) |
| Feature graphic | `feature-graphic-1024x500.png` |
| Phone screenshots | You take these (see below) |

Phone screenshots: at least 2, ideally 4–8, portrait, minimum 1080×1920. Take them from
the app (on a phone or an emulator) while signed in as a **demo employee with sample
data**, not real staff data. Good screens to use: Home, Attendance, Apply leave, Payslip
detail, Team (manager) and Approvals.

---

## 5. App content (Policy → App content)

| Section | Answer |
|---|---|
| **Privacy policy** | `https://hrms.yourcompany.com/privacy-policy` |
| **App access** | *All or some functionality is restricted* → add login details for a **demo employee** (employee code + password) that reviewers can use. Use a demo account that reports to a demo manager and has sample attendance, leave and a payslip. Do **not** use a real employee. |
| **Ads** | No ads |
| **Content rating** | Category *Utility, Productivity, Communication, or Other*. Answer **No** to violence, sexual content, gambling and controlled substances. *User-generated content shared with others?* No. *Shares location?* No. |
| **Target audience** | 18 and over |
| **News app** | No |
| **COVID-19 tracing / status** | No |
| **Government app** | No |
| **Financial features** | None (the app shows payslips and expense claims but offers no financial services) |
| **Health** | No |

### Data safety

**General questions**

| Question | Answer |
|---|---|
| Does your app collect or share any of the required user data types? | **Yes** |
| Is all of the user data encrypted in transit? | **Yes** (HTTPS only) |
| Do you provide a way for users to request that their data be deleted? | **Yes** — through HR, as described on the privacy-policy page |

**Data types collected.** For every row below, the data is **collected, not shared**, and it is **required**, not optional.

| Data type | Collected | Purpose |
|---|---|---|
| Personal info → Name | ✔ | App functionality, Account management |
| Personal info → Email address | ✔ | App functionality, Account management |
| Personal info → User IDs (employee code) | ✔ | App functionality, Account management |
| Personal info → Address, Phone number (profile) | ✔ | App functionality |
| Financial info → Other financial info (salary, payslips, expense claims) | ✔ | App functionality |
| Photos and videos → Photos (receipts the user attaches) | ✔ (optional) | App functionality |
| Files and docs (PDF attachments) | ✔ (optional) | App functionality |
| App info and performance → Crash logs | ✗ | — |
| Device or other IDs (push token, device model) | ✔ | App functionality, Account management, Security/fraud prevention |
| Location | ✗ | — |

Notes:
- "Shared" means given to third parties for their own use. Google Firebase, which only
  delivers notifications on our behalf, counts as a *service provider* and is **not** shared.
- The account-deletion requirement for apps that let users *create* accounts in the app
  doesn't apply here, because accounts are created by HR. Answer the deletion question
  as above.

### Permissions in the build

| Permission | Why |
|---|---|
| `INTERNET` | Required |
| `POST_NOTIFICATIONS` | Push notifications (Android 13+ asks the user) |

The camera and gallery are used through the system picker, so no camera or storage
permission is requested.

`READ_MEDIA_IMAGES`, `READ_MEDIA_VIDEO`, `READ_MEDIA_AUDIO` and `READ_EXTERNAL_STORAGE` are
removed from the final app in `android/app/src/main/AndroidManifest.xml` (`tools:node="remove"`).
The `open_filex` library declares them, but the app doesn't need them. This complies with
Play's photo and video permissions policy.

If Play Console still shows the warning, a release on another track (for example Internal
testing) still contains an older build. Replace it with build 2 or later on **every** track.

---

## 6. Release

1. **Testing → Internal testing → Create new release.** Upload `app-release.aab` and add
   yourself and a few HR colleagues as testers.
2. Install from the opt-in link and check:
   - sign-in;
   - applying for leave;
   - approving from a manager phone;
   - push notifications;
   - downloading a payslip.
3. **Production** (or a Closed testing track, for option C):
   - Create a release and promote the tested build.
   - Countries: India, or wherever staff are.
   - Roll out.
   - Google's first review usually takes a few days.
4. Share the Play Store link with employees.

### Updates

Bump `version:` in `pubspec.yaml`, run `build-release.ps1`, upload the new `.aab` and roll it
out. Keep `build\symbols` for each version.

---

## iPhone (later)

The code supports iOS. Publishing on the App Store needs:
- a Mac with Xcode;
- an Apple Developer account (US$99 a year);
- the bundle ID set to `com.globalspace.vodo_hrms` in Xcode;
- Push Notifications enabled, with an APNs key uploaded to Firebase.

Then run `flutter build ipa --dart-define-from-file=release-config.json` and upload with
Transporter.
