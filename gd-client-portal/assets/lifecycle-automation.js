jQuery(function($){
  $(document).on('click','.gdcp-lifecycle-action',function(e){
    e.preventDefault();
    var b=$(this), action=b.data('action'), project=b.data('project');
    if(!action||!project)return;
    b.prop('disabled',true).text('Working…');
    $.post(ajaxurl,{action:'gd_client_portal_lifecycle_action',nonce:GDCP_LIFECYCLE.nonce,project_id:project,action:action})
      .done(function(r){ if(r&&r.success){ b.text('Done'); location.reload(); } else { b.prop('disabled',false).text('Try again'); alert(r&&r.data&&r.data.message?r.data.message:'Action failed.'); } })
      .fail(function(x){ b.prop('disabled',false).text('Try again'); alert(x.responseJSON&&x.responseJSON.data&&x.responseJSON.data.message?x.responseJSON.data.message:'Action failed.'); });
  });
});
