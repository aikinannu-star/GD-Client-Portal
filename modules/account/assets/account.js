(function($){
    $(function(){
        var $form = $('.gd-account-form');
        if (!$form.length) {
            return;
        }

        // Elements
        var $avatarInput = $form.find('input[name="gd_client_portal_avatar"]');
        var $avatarWrapper = $form.find('.gd-account-avatar');
        var $img = $avatarWrapper.find('img');
        var initial = $avatarWrapper.data('initial') || '';
        var $notice = $('#gd-account-notice');

        // Avatar file change: preview + AJAX upload
        $avatarInput.on('change', function(){
            var file = this.files && this.files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function(ev){
                if ($img.length) {
                    $img.attr('src', ev.target.result);
                } else {
                    $avatarWrapper.find('.gd-avatar-placeholder').replaceWith('<img src="'+ev.target.result+'" width="96" height="96" style="border-radius:6px;border:1px solid #ddd" />');
                }
            };
            reader.readAsDataURL(file);

            if (typeof gdClientPortalAccount !== 'undefined' && gdClientPortalAccount.ajax_url) {
                var fd = new FormData();
                fd.append('action', 'gd_client_portal_avatar_upload');
                fd.append('nonce', gdClientPortalAccount.avatar_nonce);
                fd.append('avatar', file);

                $.ajax({
                    url: gdClientPortalAccount.ajax_url,
                    type: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    success: function(resp){
                        if (resp.success && resp.data && resp.data.url) {
                            if ($img.length) { $img.attr('src', resp.data.url); }
                            showNotice('Avatar uploaded', 'success');
                        } else if (resp && resp.data && resp.data.message) {
                            showNotice(resp.data.message, 'error');
                        }
                    },
                    error: function(xhr){
                        var msg = 'Upload failed';
                        try { msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : msg; } catch (e) {}
                        showNotice(msg, 'error');
                    }
                });
            }
        });

        // Remove avatar: ask user and optionally delete from library
        $form.find('#gd-avatar-remove').on('click', function(){
            if (!confirm('Remove avatar?')) return;
            var alsoDelete = confirm('Also delete the file from the media library? This is permanent.');
            $.post(gdClientPortalAccount.ajax_url, { action: 'gd_client_portal_avatar_remove', nonce: gdClientPortalAccount.avatar_nonce, delete: alsoDelete ? 1 : 0 }, function(resp){
                if (resp && resp.success) {
                    $avatarWrapper.find('img').remove();
                    $avatarWrapper.find('.gd-avatar-placeholder').remove();
                    $avatarWrapper.prepend('<div class="gd-avatar-placeholder">'+initial+'</div>');
                    showNotice('Avatar removed', 'success');
                } else {
                    var m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Could not remove avatar';
                    showNotice(m, 'error');
                }
            }, 'json');
        });

        // Intercept profile form submit and send via AJAX for live save
        $form.on('submit', function(e){
            e.preventDefault();
            var fd = new FormData($form[0]);
            // append AJAX action and nonce expected by the handler
            fd.append('action', 'gd_client_portal_account_update');
            if (typeof gdClientPortalAccount !== 'undefined' && gdClientPortalAccount.update_nonce) {
                fd.append('nonce', gdClientPortalAccount.update_nonce);
            }

            $.ajax({
                url: gdClientPortalAccount.ajax_url,
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                success: function(resp){
                    if (resp && resp.success) {
                        showNotice(resp.data && resp.data.message ? resp.data.message : 'Saved', 'success');
                        if (resp.data && resp.data.avatar_url) {
                            if ($img.length) { $img.attr('src', resp.data.avatar_url); }
                        }
                    } else {
                        var m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Save failed';
                        showNotice(m, 'error');
                    }
                },
                error: function(xhr){
                    var msg = 'Save failed';
                    try { msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : msg; } catch (e) {}
                    showNotice(msg, 'error');
                }
            });
        });

        function showNotice(msg, type){
            var $n = $notice.length ? $notice : $form.find('#gd-account-notice');
            $n.removeClass('error success').addClass(type).text(msg).show();
            setTimeout(function(){ $n.fadeOut(400,function(){ $n.text('').show().removeClass(type); }); }, 3000);
        }
    });
})(jQuery);
