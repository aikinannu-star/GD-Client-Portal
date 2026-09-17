jQuery(function ($) {
    function gdClientPortalHighlightModuleCard(moduleName) {
        $('.gd-card').removeClass('is-active');

        if (!moduleName) {
            return;
        }

        $('.gd-card[data-module="' + moduleName + '"]').addClass('is-active');
    }

    function gdClientPortalSyncModuleState() {
        var params = new URLSearchParams(window.location.search || '');
        var moduleName = params.get('module');
        gdClientPortalHighlightModuleCard(moduleName);
    }

    $('.gd-auth-tab').on('click', function () {
        var panel = $(this).data('gd-auth-tab');
        $('.gd-auth-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.gd-auth-panel').removeClass('is-active');
        $('.gd-auth-panel[data-gd-auth-panel="' + panel + '"]').addClass('is-active');
    });

    $(document).on('click', '.gd-card .gd-btn', function (e) {
        var $link = $(this);
        var moduleName = $link.data('module');
        if (!moduleName || $link.attr('target') === '_blank') {
            return;
        }

        e.preventDefault();
        gdClientPortalHighlightModuleCard(moduleName);

        var href = $link.attr('href');
        if (window.history && window.history.pushState) {
            window.history.pushState({ module: moduleName }, '', href);
        }

        if (href) {
            window.location.href = href;
        }
    });

    gdClientPortalSyncModuleState();
    $(window).on('popstate', gdClientPortalSyncModuleState);

    // AJAX login
    $(document).on('submit', '.gd-client-portal-login-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        
        // Get nonce from form field, fallback to localized value
        var nonce = $form.find('input[name="gd_client_portal_login_nonce"]').val() || 
                    (typeof GDClientPortal !== 'undefined' ? GDClientPortal.login_nonce : '');
        
        var data = {
            action: 'gd_client_portal_ajax_login',
            gd_client_portal_login: 1,
            gd_client_portal_login_nonce: nonce,
            gd_login_user: $form.find('#gd_login_user').val(),
            gd_login_pass: $form.find('#gd_login_pass').val(),
            gd_login_remember: $form.find('input[name="gd_login_remember"]').is(':checked') ? 1 : 0,
            gd_post_purchase: $form.find('input[name="gd_post_purchase"]').val() || '',
            gd_order_id: $form.find('input[name="gd_order_id"]').val() || ''
        };

        $form.find('.gd-auth-error, .gd-auth-success').remove();

        $.post(GDClientPortal.ajax_url, data, function (resp) {
            if (resp.success) {
                var msg = '<div class="gd-auth-success">' + resp.data.message + '</div>';
                $form.prepend(msg);
                if (resp.data.redirect) {
                    window.location = resp.data.redirect;
                }
            } else {
                var msg = '<div class="gd-auth-error">' + resp.data.message + '</div>';
                $form.prepend(msg);
            }
        }, 'json').fail(function() {
            var msg = '<div class="gd-auth-error">Unable to process your request. Please try again.</div>';
            $form.prepend(msg);
        });
    });

    // AJAX register
    $(document).on('submit', '.gd-client-portal-register-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        
        // Get nonce from form field, fallback to localized value
        var nonce = $form.find('input[name="gd_client_portal_register_nonce"]').val() || 
                    (typeof GDClientPortal !== 'undefined' ? GDClientPortal.register_nonce : '');
        
        var data = {
            action: 'gd_client_portal_ajax_register',
            gd_client_portal_register: 1,
            gd_client_portal_register_nonce: nonce,
            gd_first_name: $form.find('#reg_first_name').val(),
            gd_last_name: $form.find('#reg_last_name').val(),
            email: $form.find('#reg_email').val(),
            password: $form.find('#reg_password').val(),
            gd_phone: $form.find('#reg_phone').val(),
            gd_company: $form.find('#reg_company').val(),
            gd_post_purchase: $form.find('input[name="gd_post_purchase"]').val() || '',
            gd_order_id: $form.find('input[name="gd_order_id"]').val() || ''
        };

        $form.find('.gd-auth-error, .gd-auth-success').remove();

        $.post(GDClientPortal.ajax_url, data, function (resp) {
            if (resp.success) {
                var msg = '<div class="gd-auth-success">' + resp.data.message + '</div>';
                $form.prepend(msg);
                if (resp.data.redirect) {
                    window.location = resp.data.redirect;
                }
            } else {
                var msg = '<div class="gd-auth-error">' + resp.data.message + '</div>';
                $form.prepend(msg);
            }
        }, 'json').fail(function() {
            var msg = '<div class="gd-auth-error">Unable to process your request. Please try again.</div>';
            $form.prepend(msg);
        });
    });

    // Project message submit (modal)
    $(document).on('submit', '.gd-project-message-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var formData = new FormData(this);
        formData.append('action', 'gd_client_portal_add_message');

        $form.find('.gd-auth-error, .gd-auth-success').remove();

        $.ajax({
            url: GDClientPortal.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                $form.prepend('<div class="gd-auth-success">' + resp.data.message + '</div>');
                // reload modal content
                var pid = $form.find('input[name="project_id"]').val();
                gdOpenProject(pid);
            } else {
                $form.prepend('<div class="gd-auth-error">' + resp.data.message + '</div>');
            }
        });
    });

    // Stage update
    $(document).on('click', '.gd-project-stage-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var projectId = $btn.data('project-id');
        var stage = $btn.data('stage');

        $.post(GDClientPortal.ajax_url, {
            action: 'gd_client_portal_update_stage',
            project_id: projectId,
            new_stage: stage,
            _wpnonce: GDClientPortal.login_nonce // reuse a nonce for modal; could create specific nonce
        }, function (resp) {
            if (resp.success) {
                // reload modal
                gdOpenProject(projectId);
            } else {
                alert(resp.data.message || 'Unable to update stage.');
            }
        }, 'json');
    });
});
