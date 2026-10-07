# Play Store phone screenshots

The eight `0X-*.png` files (1080×1920, 9:16) are ready to upload to **Play Console → Main
store listing → Phone screenshots**, in this order.

They are rendered from the real app screens with **demo data**, so no real employee
information appears. To regenerate them after UI changes, run this from `mobile-app/`:

```bash
flutter test tool/store_screenshots_test.dart --update-goldens   # raw captures → raw/
php tool/frame_screenshots.php                                   # framed 1080×1920 → here
```

The demo data is in `tool/store_screenshots_test.dart`, and the captions are in
`tool/frame_screenshots.php`.
