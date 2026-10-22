# RealityLog Mobile

Flutter Android client for [RealityLog](https://github.com/tipusultan007/realitylog).

The app connects to the Laravel REST API at `/api/v1` and supports:

- Email/password login and API token storage in secure device storage
- Text, link, image, document, and voice capture
- Memory library, favorites, archive, search, and timeline
- Relationship suggestions and linked memories
- Reminders, collections, and account settings
- English and Bengali interface strings

## Requirements

- Flutter SDK compatible with Dart `^3.13.5`
- Android SDK
- A running RealityLog web/API instance

## Run

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` is the Android emulator alias for the host machine. For a physical device, use the host machine's LAN address:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
```

If `API_BASE_URL` is omitted, the app defaults to `http://10.0.2.2:8000/api/v1`.

## Validate

```bash
flutter analyze
flutter test
```

The localization test enforces identical English and Bengali string keys and non-empty translations.

## Scope

Android is the current mobile target. iOS is deferred until a macOS/CI build environment is available.
