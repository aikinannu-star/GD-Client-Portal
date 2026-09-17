import UIKit
import WebKit

class MainViewController: UIViewController {
    var webView: WKWebView!

    override func viewDidLoad() {
        super.viewDidLoad()
        // Capacitor normally provides the WKWebView. This is a minimal example if you embed one manually.
        let config = WKWebViewConfiguration()
        webView = WKWebView(frame: view.bounds, configuration: config)
        webView.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        view.addSubview(webView)
        // Load local content from the Capacitor www folder or remote URL as needed.
    }

    func callHandleNativeNav(_ tab: String) {
        let script = "window.handleNativeNav && window.handleNativeNav('\(tab)')"
        webView.evaluateJavaScript(script, completionHandler: nil)
    }

    func callUpdateCartCount(_ count: Int) {
        let script = "window.updateCartCount && window.updateCartCount(\(count))"
        webView.evaluateJavaScript(script, completionHandler: nil)
    }
}
