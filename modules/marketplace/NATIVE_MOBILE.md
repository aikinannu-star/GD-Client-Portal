Native mobile wrapper guide (Capacitor)

Goal
- Host the marketplace web app inside a native shell and implement a true native bottom navigation (Android BottomNavigationView / iOS UITabBar) that delegates navigation into the web view.

Why
- Native bottom nav gives platform-consistent UX and performance.
- The native tab bar can own navigation, status badges, native animations, and deep integrations (push, in-app purchases, native payments, etc.).

Quick outline (Capacitor)
1) Install Capacitor in your web project root
   - npm install @capacitor/core @capacitor/cli --save
   - npx cap init "gd-client-portal" "com.yourcompany.gdclientportal"
2) Add native platforms
   - npx cap add android
   - npx cap add ios
3) Configure webDir in capacitor.config.json to point to your built web assets (e.g., "webDir": "build" or "dist").
4) Build web app and copy to native project
   - npm run build
   - npx cap copy
   - npx cap open android  # or ios

Native bottom nav examples

Android (Kotlin)
- Add a BottomNavigationView to your activity XML, keep the Capacitor/Bridge WebView full-screen but above the bottom nav (or adjust layout so web view sits above it).
- Example layout (res/layout/activity_main.xml):

<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:app="http://schemas.android.com/apk/res-auto"
    android:orientation="vertical"
    android:layout_width="match_parent"
    android:layout_height="match_parent">

    <FrameLayout
        android:id="@+id/web_container"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        android:layout_weight="1" />

    <com.google.android.material.bottomnavigation.BottomNavigationView
        android:id="@+id/bottom_nav"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        app:menu="@menu/bottom_nav_menu" />
</LinearLayout>

- In your MainActivity (Kotlin) where Capacitor loads the WebView, forward menu events to the web view:

binding.bottomNav.setOnItemSelectedListener { item ->
    when(item.itemId) {
        R.id.nav_home -> {
            webView.post { webView.evaluateJavascript("window.handleNativeNav && window.handleNativeNav('home')", null) }
            true
        }
        R.id.nav_search -> {
            webView.post { webView.evaluateJavascript("window.handleNativeNav && window.handleNativeNav('search')", null) }
            true
        }
        R.id.nav_cart -> {
            webView.post { webView.evaluateJavascript("window.handleNativeNav && window.handleNativeNav('cart')", null) }
            true
        }
        R.id.nav_account -> {
            webView.post { webView.evaluateJavascript("window.handleNativeNav && window.handleNativeNav('orders')", null) }
            true
        }
        else -> false
    }
}

iOS (Swift)
- Use a UITabBarController or add a UITabBar below the web view. When a tab is selected, call evaluateJavaScript on the webView:

tabBarController?.selectedIndex = 2
let script = "window.handleNativeNav && window.handleNativeNav('cart')"
webView.evaluateJavaScript(script, completionHandler: nil)

Web-side integration (already included)
- `modules/marketplace/assets/native-bridge.js` exposes `window.handleNativeNav` and hides the web bottom nav when it detects a native container (Capacitor or ReactNativeWebView). The marketplace template now enqueues this script.

Notes & best practices
- Use a simple message bridge and keep navigation commands idempotent.
- Hide the web bottom nav in native builds (the provided `native-bridge.js` does this by UA or Capacitor detection).
- For deeper integrations (push, camera, payments), implement Capacitor plugins or native code that calls the web via `evaluateJavascript` or uses Capacitor Plugin calls.
- Test on-device; WebView rendering and safe-area insets differ per device. Use `env(safe-area-inset-bottom)` in CSS for iOS.

Testing
1. Build web assets and copy to the native project (`npx cap copy`).
2. Run on emulator/device and tap native bottom nav; verify the web view responds (search focuses, cart opens, etc.).
3. Confirm the web bottom nav is hidden in the native wrapper.

If you want, I can:
- Scaffold a minimal Capacitor project with the sample Android/iOS code and the JS bridge wired (requires native CLI tooling and platform SDKs on your machine).
- Create a small example plugin for badge updates (native->web) so the cart count is kept in sync with native state.

