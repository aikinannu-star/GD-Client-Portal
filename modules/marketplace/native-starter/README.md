Capacitor starter (minimal)

Overview
- This folder is a minimal starter to integrate the marketplace web app into a native Capacitor shell.
- It does NOT include native SDKs — run the commands below on your machine with Node, Java/Android SDK, and Xcode set up.

Quick start
1. Install dependencies

```bash
cd modules/marketplace/native-starter
npm install
```

2. Initialize Capacitor (if not already)

```bash
npm run cap:init
# or
npx cap init gd-client-portal com.example.gdclientportal --web-dir=www
```

3. Build your web assets into `www` (copy the marketplace HTML/CSS/JS)

4. Add platforms

```bash
npx cap add android
npx cap add ios
```

5. Copy web assets and open native IDE

```bash
npx cap copy
npx cap open android
npx cap open ios
```

Native integration notes
- The web app includes `modules/marketplace/assets/native-bridge.js` which exposes `window.handleNativeNav(tab)` and `window.updateCartCount(count)`.
- From native code call JavaScript in the WebView to control the web app, for example:

Android (Kotlin):
```kotlin
webView.post {
  webView.evaluateJavascript("window.handleNativeNav && window.handleNativeNav('cart')", null)
}
```

iOS (Swift, WKWebView):
```swift
let script = "window.handleNativeNav && window.handleNativeNav('cart')"
webView.evaluateJavaScript(script, completionHandler: nil)
```

Safe-area & styling
- Use CSS safe-area insets for iOS bottom padding: `padding-bottom: env(safe-area-inset-bottom);`.

If you want, I can also generate a small Android activity and iOS view controller snippet and place them in this folder. Run `npm install` and follow the steps above to test on device.
