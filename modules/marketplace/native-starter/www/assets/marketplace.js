(function($){
    $(function(){
        // defensive fallback for localized config
        var gp = window.gdClientPortalMarketplace || {};
        gp.ajax_url = gp.ajax_url || (window.ajaxurl || '/wp-admin/admin-ajax.php');
        gp.nonce = gp.nonce || '';
        gp.rest_base = gp.rest_base || '/wp-json/gd-client-portal/v1/marketplace';
        var G = gp;

        // Toast helper (non-blocking)
        function ensureToastContainer(){
            var container = document.getElementById('gd-mp-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'gd-mp-toast-container';
                container.setAttribute('role','status');
                container.setAttribute('aria-live','polite');
                container.setAttribute('aria-atomic','true');
                container.style.position = 'fixed';
                container.style.right = '16px';
                container.style.bottom = '16px';
                container.style.zIndex = '99999';
                // screen-reader helper for announcing toasts
                var sr = document.createElement('span');
                sr.className = 'gd-mp-sr';
                sr.style.position = 'absolute';
                sr.style.left = '-9999px';
                sr.style.width = '1px';
                sr.style.height = '1px';
                sr.style.overflow = 'hidden';
                container.appendChild(sr);
                document.body.appendChild(container);
            }
            return container;
        }
        function showToast(msg, type){
            if (!msg) msg = '';
            var container = ensureToastContainer();
            var t = document.createElement('div');
            t.className = 'gd-mp-toast ' + (type === 'success' ? 'gd-mp-toast-success' : 'gd-mp-toast-error');
            t.textContent = msg;
            container.appendChild(t);
            // update sr helper to trigger announcement
            try {
                var sr = container.querySelector('.gd-mp-sr');
                if (sr) { sr.textContent = msg; }
            } catch (e) {}
            // auto remove
            setTimeout(function(){ t.classList.add('gd-mp-toast-hide'); setTimeout(function(){ try{ container.removeChild(t);}catch(e){} }, 400); }, 4000);
        }
        function showError(msg){ if (!msg) msg = 'Unable to add item to cart.'; showToast(msg, 'error'); }

        // Add to cart handler (delegated)
        $('body').on('click', '.gd-market-add-to-cart', function(e){
            e.preventDefault();
            var btn = $(this);
            var id = btn.data('id') || '';
            var sku = btn.data('sku') || '';
            if (!id && !sku) { showError('Item identifier missing.'); return; }
            var originalText = btn.text();
            btn.prop('disabled', true).addClass('loading').attr('aria-busy','true').text('Adding...');

            var actionName = (G && G.paystack_enabled) ? 'gd_client_portal_marketplace_create_paystack_checkout' : 'gd_client_portal_marketplace_add_to_cart';
            var post = {
                action: actionName
            };
            if (id) post.id = id;
            if (sku) post.sku = sku;
            if (G && G.nonce) {
                post.nonce = G.nonce;
            }

            $.post(G.ajax_url, post, function(resp){
                try {
                    if (!resp) { showError('Empty response from server'); btn.prop('disabled', false).text(originalText); return; }
                    if (resp.success) {
                        // redirect to cart if provided
                        var url = (resp.data && resp.data.redirect) ? resp.data.redirect : null;
                        if (url) { window.location = url; return; }
                                // update cart count if possible, otherwise reload page
                                try {
                                    var countEl = document.querySelector('.gd-mp-cart-count');
                                            if (countEl) {
                                                var newCount = null;
                                                if (resp.data && typeof resp.data.cart_count !== 'undefined') {
                                                    newCount = parseInt(resp.data.cart_count, 10);
                                                }
                                                if (newCount === null || isNaN(newCount)) {
                                                    // increment visually
                                                    var cur = parseInt(countEl.textContent || '0', 10) || 0;
                                                    newCount = cur + 1;
                                                }
                                                var previous = parseInt(countEl.textContent || '0', 10) || 0;
                                                countEl.textContent = String(newCount);
                                                // pulse when count increases
                                                if (newCount > previous) {
                                                    try { countEl.classList.remove('pulse'); void countEl.offsetWidth; countEl.classList.add('pulse'); } catch(e){}
                                                    setTimeout(function(){ try{ countEl.classList.remove('pulse'); }catch(e){} }, 800);
                                                }
                                                showToast((resp.data && resp.data.message) ? resp.data.message : 'Added to cart', 'success');
                                                // restore button state briefly then leave
                                                setTimeout(function(){ btn.prop('disabled', false).removeClass('loading').removeAttr('aria-busy').text(originalText); }, 600);
                                                return;
                                            }
                                } catch (e) {
                                    // fallback to reload when update fails
                                    console.warn('Failed to update cart count', e);
                                }
                                window.location.reload();
                        return;
                    }
                    var msg = (resp.data && resp.data.message) ? resp.data.message : (resp.message || 'Failed to add to cart');
                        showError(msg);
                        btn.prop('disabled', false).removeClass('loading').removeAttr('aria-busy').text(originalText);
                } catch (ex) {
                    console.error('Marketplace post-callback exception', ex, resp);
                    showError('Unexpected response from server');
                    btn.prop('disabled', false).removeClass('loading').removeAttr('aria-busy').text(originalText);
                }
            }, 'json').fail(function(xhr, status, err){
                var txt = (xhr && xhr.responseText) ? xhr.responseText : (err || status);
                console.error('Marketplace AJAX failed', status, err, txt);
                // try to extract JSON error message
                try {
                    var j = JSON.parse(txt);
                    if (j && j.data && j.data.message) showError(j.data.message);
                    else showError(txt);
                } catch(e) {
                    showError(txt);
                }
                btn.prop('disabled', false).removeClass('loading').removeAttr('aria-busy').text(originalText);
            });
        });

        // Drawer toggle and mini-search
        var drawer = document.getElementById('gd-mp-drawer');
        var _prevFocus = null;
        function openDrawer(){
            if (!drawer) return;
            _prevFocus = document.activeElement;
            drawer.setAttribute('aria-hidden','false');
            drawer.classList.add('open');
            document.querySelector('.gd-mp-menu-toggle')?.setAttribute('aria-expanded','true');
            // focus first focusable element
            var focusable = drawer.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])');
            if (focusable && focusable.length) { focusable[0].focus(); }
            document.addEventListener('keydown', _drawerKeyHandler, true);
        }
        function closeDrawer(){
            if (!drawer) return;
            drawer.setAttribute('aria-hidden','true');
            drawer.classList.remove('open');
            document.querySelector('.gd-mp-menu-toggle')?.setAttribute('aria-expanded','false');
            document.removeEventListener('keydown', _drawerKeyHandler, true);
            try { if (_prevFocus) _prevFocus.focus(); } catch(e){}
        }

        function _drawerKeyHandler(e){
            // ESC closes
            if (e.key === 'Escape' || e.key === 'Esc') { closeDrawer(); e.preventDefault(); return; }
            if (e.key !== 'Tab') return;
            // focus trap
            var focusable = Array.prototype.slice.call(drawer.querySelectorAll('a, button, input, [tabindex]:not([tabindex="-1"])')).filter(function(n){ return !n.hasAttribute('disabled'); });
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
            else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
        }
        document.body.addEventListener('click', function(ev){
            var t = ev.target;
            if (t && t.closest && t.closest('.gd-mp-menu-toggle')) { ev.preventDefault(); openDrawer(); }
            if (t && t.closest && t.closest('.gd-mp-drawer-close')) { ev.preventDefault(); closeDrawer(); }
        }, false);

        // mini search: client-side filter
        (function(){
            var input = document.getElementById('gd-mp-mini-search');
            if (!input) return;
            var timeout = null;
            input.addEventListener('input', function(){
                clearTimeout(timeout);
                timeout = setTimeout(function(){
                    var q = input.value.trim().toLowerCase();
                    var items = document.querySelectorAll('.gd-market-item');
                    items.forEach(function(it){
                        var title = (it.querySelector('.gd-market-head strong')?.textContent||'').toLowerCase();
                        var desc = (it.querySelector('p')?.textContent||'').toLowerCase();
                        var show = !q || title.indexOf(q) !== -1 || desc.indexOf(q) !== -1;
                        it.style.display = show ? '' : 'none';
                    });
                }, 200);
            });
        })();

        // search toggle for small screens
        (function(){
            var toggle = document.querySelector('.gd-mp-search-toggle');
            var label = document.querySelector('.gd-mp-search-label');
            var input = document.getElementById('gd-mp-mini-search');
            if (!toggle || !label) return;
            toggle.addEventListener('click', function(e){
                e.preventDefault();
                var open = label.style.display !== 'none' && label.classList.contains('open');
                if (open) {
                    label.classList.remove('open');
                    label.style.display = 'none';
                    toggle.setAttribute('aria-expanded','false');
                } else {
                    label.classList.add('open');
                    label.style.display = 'flex';
                    toggle.setAttribute('aria-expanded','true');
                    try { input.focus(); } catch(e){}
                }
            });
        })();

        // client-side category tabs & REST-driven render
        (function(){
            var tabs = document.querySelectorAll('.gd-mp-cat-tab');
            var grid = document.getElementById('gd-mp-grid');
            var perpageSel = document.getElementById('gd-mp-perpage');
            var filterSel = document.getElementById('gd-mp-filter');
            var paginationNav = document.getElementById('gd-mp-pagination');
            var loadMoreBtn = document.getElementById('gd-mp-loadmore');
            var statusCounter = document.querySelector('.gd-marketplace-controls [aria-live]');

            function renderItems(data, append){
                if (!grid) return;
                if (!append) grid.innerHTML = '';
                data.items.forEach(function(it){
                    var art = document.createElement('article');
                    art.className = 'gd-market-item';
                    art.setAttribute('data-id', it.id);
                    art.setAttribute('data-sku', it.sku || '');
                    art.setAttribute('data-cat', it.category || '');
                    art.setAttribute('role','article');
                    art.setAttribute('aria-labelledby','mp-title-'+it.id);

                    var imgHtml = '';
                    if (it.image) imgHtml = '<div class="gd-market-image"><img src="'+escAttr(it.image)+'" alt="'+escAttr(it.title || '')+'"/></div>';
                    var head = '<div class="gd-market-head"><strong id="mp-title-'+escAttr(it.id)+'">'+escAttr(it.title || '')+'</strong><span class="gd-market-price">$'+(parseFloat(it.price||0).toFixed(2))+'</span></div>';
                    var desc = '<p class="gd-market-desc">'+escAttr(it.description||'')+'</p>';
                    var btn = '<button class="gd-mp-add-ico" aria-label="Add '+escAttr(it.title||'')+' to cart" data-sku="'+escAttr(it.sku||'')+'" data-id="'+escAttr(it.id)+'">'+
                              '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M6 6h15l-1.5 9h-11z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1" fill="currentColor"/><circle cx="18" cy="20" r="1" fill="currentColor"/></svg></button>';
                    art.innerHTML = imgHtml + head + desc + btn;
                    grid.appendChild(art);
                });

                // update status count
                if (statusCounter && typeof data.total !== 'undefined') {
                    statusCounter.textContent = data.total + ' items';
                }

                // update pagination
                if (paginationNav) {
                    var html = '';
                    if (data.pages && data.pages > 1) {
                        for (var i=1;i<=data.pages;i++){
                            if (i === data.page) html += '<span class="button current" aria-current="page">'+i+'</span> ';
                            else html += '<a class="button" href="#" data-page="'+i+'">'+i+'</a> ';
                        }
                    }
                    paginationNav.innerHTML = html;
                }

                // update load more
                if (loadMoreBtn) {
                    if (data.pages > data.page) {
                        loadMoreBtn.style.display = '';
                        loadMoreBtn.setAttribute('data-next-page', data.page+1);
                    } else {
                        loadMoreBtn.style.display = 'none';
                    }
                }
            }

            function fetchAndRender(page, perpage, cat, append) {
                page = page || 1; perpage = perpage || (perpageSel ? parseInt(perpageSel.value||6,10) : 6); cat = cat || '';
                var url = G.rest_base + '?page='+page+'&perpage='+perpage+(cat?('&cat='+encodeURIComponent(cat)):'');
                $.get(url, function(resp){
                    if (resp && resp.items) {
                        renderItems(resp, append);
                    }
                }, 'json').fail(function(err){ console.warn('Failed to load marketplace items', err); });
            }

            if (tabs && tabs.length) {
                tabs.forEach(function(b){
                    b.addEventListener('click', function(e){
                        var cat = b.getAttribute('data-cat') || '';
                        // mark active
                        tabs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-pressed','false'); });
                        b.classList.add('active'); b.setAttribute('aria-pressed','true');
                        // fetch page 1 for category
                        fetchAndRender(1, perpageSel ? parseInt(perpageSel.value||6,10) : 6, cat, false);
                    });
                });
                // initial mark
                try { var current = (new URL(window.location.href)).searchParams.get('gd_mp_cat') || ''; if (current) { var act = document.querySelector('.gd-mp-cat-tab[data-cat="'+current+'"]'); if (act) act.classList.add('active'); } } catch(e){}
            }

            // perpage / filter selects
            if (perpageSel) perpageSel.addEventListener('change', function(){ fetchAndRender(1, parseInt(perpageSel.value||6,10), (new URL(window.location.href)).searchParams.get('gd_mp_cat')||'', false); });
            if (filterSel) filterSel.addEventListener('change', function(){ fetchAndRender(1, perpageSel ? parseInt(perpageSel.value||6,10) : 6, filterSel.value||'', false); });

            // pagination links (delegated)
            if (paginationNav) paginationNav.addEventListener('click', function(ev){ var a = ev.target.closest('a[data-page]'); if (a){ ev.preventDefault(); fetchAndRender(parseInt(a.getAttribute('data-page'),10)||1, perpageSel?parseInt(perpageSel.value||6,10):6, (new URL(window.location.href)).searchParams.get('gd_mp_cat')||'', false); } });

            // load more handler
            if (loadMoreBtn) loadMoreBtn.addEventListener('click', function(){ var next = parseInt(loadMoreBtn.getAttribute('data-next-page')||1,10); var cat = (new URL(window.location.href)).searchParams.get('gd_mp_cat')||''; fetchAndRender(next, perpageSel?parseInt(perpageSel.value||6,10):6, cat, true); });
        })();

        // bottom nav behavior
        (function(){
            var cartBtn = document.querySelector('.gd-mp-bottom-cart');
            if (cartBtn) {
                cartBtn.addEventListener('click', function(e){ e.preventDefault(); var cartToggle = document.querySelector('.gd-mp-cart-btn'); if (cartToggle) cartToggle.click(); });
            }
            var searchToggle = document.querySelector('.gd-mp-bottom-search');
            if (searchToggle) {
                searchToggle.addEventListener('click', function(e){ e.preventDefault(); var st = document.querySelector('.gd-mp-search-toggle'); if (st) st.click(); });
            }
        })();

        // fetch initial cart count and update header
        (function(){
            var countEl = document.querySelector('.gd-mp-cart-count');
            if (!countEl) return;
            try {
                $.get(G.ajax_url, { action: 'gd_client_portal_marketplace_get_cart_count' }, function(resp){
                    if (resp && resp.success && resp.data && typeof resp.data.cart_count !== 'undefined') {
                        countEl.textContent = String(parseInt(resp.data.cart_count,10) || 0);
                    }
                }, 'json').fail(function(){});
            } catch(e) {}
        })();

        // simple helpers used elsewhere (kept for rest load-more)
        function escapeHtml(text) { return String(text).replace(/[&"'<>]/g, function (s) { return {'&':'&amp;','"':'&quot;','\'':'&#39;','<':'&lt;','>':'&gt;'}[s]; }); }
        function escAttr(t){ return escapeHtml(t); }
    });
})(jQuery);
