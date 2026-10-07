# Builds the Play Store bundle (and an APK for direct install / testing).
#
#   1. Copy release-config.example.json to release-config.json and fill in HRMS_URL
#      (and the FIREBASE_* values if push notifications are set up).
#   2. Bump "version:" in pubspec.yaml (e.g. 1.0.1+2) before every Play Store upload.
#   3. powershell -ExecutionPolicy Bypass -File build-release.ps1
#
# Output: build\app\outputs\bundle\release\app-release.aab  (upload this to Play Console)
#         build\app\outputs\flutter-apk\app-release.apk
#         build\symbols\                                 (keep: needed to read crash reports)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Test-Path 'release-config.json')) {
    throw 'release-config.json not found. Copy release-config.example.json to release-config.json and fill it in.'
}

$config = Get-Content 'release-config.json' -Raw | ConvertFrom-Json
if (-not ($config.HRMS_URL -match '^https://')) {
    throw "HRMS_URL must be an https:// address (got '$($config.HRMS_URL)'). Release builds cannot use plain http."
}
if ($config.HRMS_URL -match 'yourcompany') {
    throw 'HRMS_URL still has the example value. Set your real HRMS address in release-config.json.'
}
if (-not (Test-Path 'android\key.properties')) {
    throw 'android\key.properties (upload signing key) is missing. See PLAY_STORE.md.'
}

$version = (Select-String -Path 'pubspec.yaml' -Pattern '^version:\s*(.+)$').Matches[0].Groups[1].Value
Write-Host "Building VODO HRMS $version for $($config.HRMS_URL)" -ForegroundColor Cyan

# Stop Gradle/Kotlin daemons left over from earlier builds: a long-running daemon holding
# gigabytes of RAM is what makes release builds crash with "insufficient memory".
if (Test-Path 'android\gradlew.bat') { & android\gradlew.bat --stop -p android | Out-Null }

flutter clean
flutter pub get
flutter test
if ($LASTEXITCODE -ne 0) { throw 'Tests failed.' }

$common = @('--release', '--obfuscate', '--split-debug-info=build/symbols', '--dart-define-from-file=release-config.json')

flutter build appbundle @common
if ($LASTEXITCODE -ne 0) { throw 'App bundle build failed.' }

flutter build apk @common
if ($LASTEXITCODE -ne 0) { throw 'APK build failed.' }

Write-Host ''
Write-Host 'Done.' -ForegroundColor Green
Write-Host '  Play Store bundle : build\app\outputs\bundle\release\app-release.aab'
Write-Host '  Installable APK   : build\app\outputs\flutter-apk\app-release.apk'
Write-Host '  Debug symbols     : build\symbols  (keep a copy per version)'
