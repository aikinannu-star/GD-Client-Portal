document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.gd-calendar-form').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault(); const status=form.querySelector('.gd-calendar-form-status'); status.textContent='Saving…';
    const data=new FormData(form); data.append('_wpnonce',form.closest('.gd-calendar-app')?.dataset.nonce||'');
    try{const r=await fetch(window.ajaxurl||'/wp-admin/admin-ajax.php',{method:'POST',body:data,credentials:'same-origin'});const j=await r.json(); if(!j.success) throw new Error(j.data||'Unable to save.'); status.textContent=j.data?.message||'Saved.'; setTimeout(()=>window.location.reload(),500);}catch(err){status.textContent=err.message||'Unable to save.';}
  }));
});
