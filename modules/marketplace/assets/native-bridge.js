(function(){
    // Native bridge for Capacitor / WebView wrappers
    // Hides web-only bottom nav when running inside a native container

    function isNativeContainer(){
        // Capacitor
        try { if (window.Capacitor && window.Capacitor.isNative) return true; } catch(e){}
        // React Native WebView
        try { if (window.ReactNativeWebView) return true; } catch(e){}
        // Custom UA check (replace 'gd-client-portal-app' with your native UA identifier if set)
        try { if (navigator && navigator.userAgent && navigator.userAgent.indexOf('gd-client-portal-app') !== -1) return true; } catch(e){}
        return false;
    }

    function hideWebBottomNav(){
        try {
            var el = document.querySelector('.gd-mp-bottom-nav');
            if (el) el.style.display = 'none';
        } catch(e){}
    }

    // Expose handler that native apps can call to control web navigation
    window.handleNativeNav = window.handleNativeNav || function(tab){
        try{
            switch(String(tab)){
                case 'home': window.location.href = '/marketplace'; break;
                case 'search': document.getElementById('gd-mp-mini-search')?.focus(); break;
                case 'cart': window.location = (window.gdClientPortalMarketplace && window.gdClientPortalMarketplace.cart_url) ? window.gdClientPortalMarketplace.cart_url : '/cart'; break;
                case 'orders': window.location.href = '/marketplace?view=orders'; break;
                case 'openDrawer': document.getElementById('gd-mp-drawer') && document.getElementById('gd-mp-drawer').dispatchEvent(new Event('open')); break;
                default: console.info('handleNativeNav: unknown', tab); break;
            }
        } catch(e){ console.warn('handleNativeNav error', e); }
    };

    // If running inside native container, hide web bottom nav
    if (isNativeContainer()){
        hideWebBottomNav();
    }

    // If using Capacitor, expose a small listener for messages from native code
    if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Bridge) {
        // custom plugin flow if you implement one; placeholder
    }

    // Expose a simple badge-sync API for native -> web updates
    window.updateCartCount = window.updateCartCount || function(count, options){
        try {
            var n = parseInt(count, 10);
            if (isNaN(n)) return;
            var el = document.querySelector('.gd-mp-cart-count');
            if (!el) return;
            var prev = parseInt(el.textContent||'0', 10) || 0;
            el.textContent = String(n);
            // optional pulse animation when increased
            var animate = !(options && options.animate === false);
            if (animate && n > prev) {
                try { el.classList.remove('pulse'); void el.offsetWidth; el.classList.add('pulse'); } catch(e){}
                setTimeout(function(){ try{ el.classList.remove('pulse'); }catch(e){} }, 800);
            }
        } catch (e) { console.warn('updateCartCount error', e); }
    };

    // convenience alias for older name
    window.setCartCount = window.setCartCount || window.updateCartCount;
})();
