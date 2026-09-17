
(function($){'use strict';
function status($el,msg){$el.text(msg||'');}
$(document).on('submit','.gd-workspace-message-form',function(e){e.preventDefault();var f=this,$f=$(f),fd=new FormData(f);fd.append('action','gd_client_portal_add_message');var $s=$f.find('.gd-workspace-action-status');status($s,GDProjectWorkspace.messages.saving);$.ajax({url:GDProjectWorkspace.ajax_url,type:'POST',data:fd,contentType:false,processData:false,dataType:'json'}).done(function(r){if(r.success){status($s,r.data.message||GDProjectWorkspace.messages.saved);setTimeout(function(){window.location.reload();},500);}else status($s,r.data&&r.data.message?r.data.message:GDProjectWorkspace.messages.error);}).fail(function(){status($s,GDProjectWorkspace.messages.error);});});
$(document).on('submit','.gd-workspace-requirements-form',function(e){e.preventDefault();var f=this,$f=$(f),fd=new FormData(f),$s=$f.find('.gd-workspace-action-status');status($s,GDProjectWorkspace.messages.saving);$.ajax({url:GDProjectWorkspace.ajax_url,type:'POST',data:fd,contentType:false,processData:false,dataType:'json'}).done(function(r){if(r.success){status($s,r.data.message||GDProjectWorkspace.messages.saved);setTimeout(function(){window.location.reload();},500);}else status($s,r.data&&r.data.message?r.data.message:GDProjectWorkspace.messages.error);}).fail(function(){status($s,GDProjectWorkspace.messages.error);});});
$(document).on('click','.gd-workspace-update-stage',function(){var b=$(this),wrap=b.closest('.gd-workspace-stage-actions'),sel=wrap.find('select'),$s=wrap.find('.gd-workspace-action-status');b.prop('disabled',true);status($s,GDProjectWorkspace.messages.saving);$.post(GDProjectWorkspace.ajax_url,{action:'gd_client_portal_update_stage',project_id:sel.data('project-id'),new_stage:sel.val(),_wpnonce:GDProjectWorkspace.stage_nonce},function(r){if(r.success){status($s,r.data.message||GDProjectWorkspace.messages.saved);setTimeout(function(){window.location.reload();},400);}else status($s,r.data&&r.data.message?r.data.message:GDProjectWorkspace.messages.error);},'json').fail(function(){status($s,GDProjectWorkspace.messages.error);}).always(function(){b.prop('disabled',false);});});
})(jQuery);
(function($){
  function approvalPost(data, statusEl){
    statusEl.text(GDProjectWorkspace.messages.saving);
    $.post(GDProjectWorkspace.ajax_url, data).done(function(r){
      statusEl.text(r && r.success ? r.data.message : (r && r.data ? r.data : GDProjectWorkspace.messages.error));
      if(r && r.success) setTimeout(function(){ window.location.reload(); }, 650);
    }).fail(function(){ statusEl.text(GDProjectWorkspace.messages.error); });
  }
  $(document).on('click','.gd-submit-review',function(){
    var btn=$(this), box=btn.closest('.gd-approval-empty'), status=box.find('.gd-workspace-action-status');
    approvalPost({action:'gd_client_portal_submit_for_review',project_id:btn.data('project-id'),_wpnonce:$('.gd-approval-panel input[name="_wpnonce"]').first().val()},status);
  });
  $(document).on('click','.gd-approval-action',function(){
    var btn=$(this), form=btn.closest('form'), status=form.find('.gd-workspace-action-status');
    approvalPost({action:'gd_client_portal_decide_approval',project_id:form.find('[name="project_id"]').val(),decision:btn.data('decision'),comment:form.find('[name="comment"]').val(),_wpnonce:form.find('[name="_wpnonce"]').val()},status);
  });
})(jQuery);

jQuery(function($){
  $(document).on('submit','.gd-delivery-upload-form',function(e){
    e.preventDefault(); var f=$(this), status=f.find('.gd-workspace-action-status'); var fd=new FormData(this); fd.append('action','gd_client_portal_delivery_upload'); status.text('Publishing…');
    $.ajax({url:window.ajaxurl||'/wp-admin/admin-ajax.php',type:'POST',data:fd,processData:false,contentType:false}).done(function(r){status.text(r.success?r.data.message:(r.data||'Unable to publish.')); if(r.success) setTimeout(function(){location.reload();},700);}).fail(function(){status.text('Unable to publish the deliverable.');});
  });
  $(document).on('click','.gd-finalize-delivery',function(){
    var b=$(this), status=b.siblings('.gd-workspace-action-status'); if(!window.confirm('Mark the current deliverable as the final delivery and close this project?')) return;
    status.text('Finalizing…'); $.post(window.ajaxurl||'/wp-admin/admin-ajax.php',{action:'gd_client_portal_finalize_delivery',project_id:b.data('project-id'),_wpnonce:b.data('nonce')},function(r){status.text(r.success?r.data.message:(r.data||'Unable to finalize.')); if(r.success) setTimeout(function(){location.reload();},700);});
  });
});
