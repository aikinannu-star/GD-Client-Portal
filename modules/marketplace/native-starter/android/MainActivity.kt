package com.example.gdclientportal

import android.os.Bundle
import com.getcapacitor.BridgeActivity

class MainActivity : BridgeActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // If you use a custom layout with BottomNavigationView, call setContentView here
        // setContentView(R.layout.activity_main)
        // Wire up your bottom nav to call sendNavToWeb("home"|"search"|"cart"|"orders")
    }

    // Convenience helpers to call JS inside the Capacitor WebView
    fun sendNavToWeb(tab: String) {
        val script = "window.handleNativeNav && window.handleNativeNav('$tab')"
        this.bridge?.webView?.post {
            this.bridge?.webView?.evaluateJavascript(script, null)
        }
    }

    fun updateCartCount(count: Int) {
        val script = "window.updateCartCount && window.updateCartCount($count)"
        this.bridge?.webView?.post {
            this.bridge?.webView?.evaluateJavascript(script, null)
        }
    }
}
