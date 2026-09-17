Android native snippet (Kotlin)

Layout (res/layout/activity_main.xml):

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

MainActivity (Kotlin) - send nav events to WebView

```kotlin
// assume `webView` is the Capacitor WebView instance
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
```

Badge sync (native -> web)

```kotlin
// when native updates cart count:
val script = "window.updateCartCount && window.updateCartCount(${cartCount})"
webView.post { webView.evaluateJavascript(script, null) }
```
