jQuery(function($){
  // Use localized globals created below by the module when available.
  $(document).on('click','.gd-pay-invoice',function(e){
    e.preventDefault(); const b=$(this), id=b.data('id');
    if(!window.GDCPPayments){ b.prop('disabled',false).text('Pay now'); return; }
    b.prop('disabled',true).text('Opening…');
    $.post(GDCPPayments.ajax_url,{action:'gd_client_portal_payment_initialize',nonce:GDCPPayments.nonce,invoice_id:id})
      .done(r=>{if(r.success&&r.data.url){window.location.href=r.data.url;}else{alert(r.data?.message||'Unable to start payment.');b.prop('disabled',false).text('Pay now');}})
      .fail(()=>{alert('Unable to start payment. Please try again.');b.prop('disabled',false).text('Pay now');});
  });
  $(document).on('click','.gd-quote-invoice',function(){const b=$(this),id=b.data('id');b.prop('disabled',true).text('Creating…');$.post(GDCPPayments.ajax_url,{action:'gd_client_portal_payment_quote_to_invoice',nonce:GDCPPayments.billing_nonce,quote_id:id}).done(r=>{alert(r.data?.message||'Done.');if(r.success)location.reload();else b.prop('disabled',false).text('Convert to Invoice');}).fail(()=>{alert('Unable to convert quote.');b.prop('disabled',false).text('Convert to Invoice');});});
  $('#gd-payment-manual-form').on('submit',function(e){e.preventDefault();const f=$(this), out=$('#gd-payment-manual-result');out.text('Saving…');$.post(GDCPPayments.ajax_url,f.serialize()).done(r=>{out.text(r.success?r.data.message:(r.data?.message||'Unable to save.'));if(r.success)setTimeout(()=>location.reload(),700);}).fail(()=>out.text('Unable to save payment.'));});
});
