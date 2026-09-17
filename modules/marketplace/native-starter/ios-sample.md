iOS native snippet (Swift)

Use a `WKWebView` (Capacitor uses a WKWebView under the hood). When a native tab is selected, call JavaScript:

```swift
// badge update example
let script = "window.updateCartCount && window.updateCartCount(\(cartCount))"
webView.evaluateJavaScript(script) { (result, error) in
    if let e = error { print("JS error", e) }
}

// navigate to cart
let navScript = "window.handleNativeNav && window.handleNativeNav('cart')"
webView.evaluateJavaScript(navScript, completionHandler: nil)
```

Safe-area layout
- Use `additionalSafeAreaInsets` or constraints so the web view content doesn't sit under the native tab bar.
