document.addEventListener('click', function(e){
 const btn=e.target.closest('.gd-operation-action'); if(!btn) return;
 e.preventDefault(); if(btn.disabled) return; btn.disabled=true; btn.classList.add('is-working');
 const data=new URLSearchParams({action:'gd_client_portal_operations_action',project_id:btn.dataset.projectId,operation:btn.dataset.operation,_wpnonce:btn.dataset.nonce});
 fetch(window.ajaxurl || (window.location.origin + '/wp-admin/admin-ajax.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()})
 .then(r=>r.json()).then(res=>{ if(res.success){btn.textContent='✓ '+(res.data.message||'Done'); btn.classList.add('is-success'); setTimeout(()=>location.reload(),700);} else {alert((res.data&&res.data.message)||res.data||'Action failed.'); btn.disabled=false; btn.classList.remove('is-working');} })
 .catch(()=>{alert('Unable to complete the action.');btn.disabled=false;btn.classList.remove('is-working');});
});

document.addEventListener('click', function(e){
 const btn=e.target.closest('.gd-assignment-action'); if(!btn) return;
 const row=btn.closest('.gd-operation-item'); const select=row && row.querySelector('.gd-assignment-user'); const userId=select && select.value;
 if(!userId){ alert('Select a team member first.'); return; }
 btn.disabled=true;
 const data=new URLSearchParams({action:'gd_client_portal_assignment_action',project_id:btn.dataset.projectId,user_id:userId,role:btn.dataset.role||'lead',assignment_action:'assign',_wpnonce:btn.dataset.nonce});
 fetch(window.ajaxurl || (window.location.origin + '/wp-admin/admin-ajax.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()})
 .then(r=>r.json()).then(res=>{ if(res.success){btn.textContent='✓ Assigned'; setTimeout(()=>location.reload(),500);} else {alert((res.data&&res.data.message)||res.data||'Assignment failed.');btn.disabled=false;} })
 .catch(()=>{alert('Unable to complete the assignment.');btn.disabled=false;});
});


document.addEventListener('click', function(e){
 const btn=e.target.closest('.gd-sla-action'); if(!btn) return;
 const row=btn.closest('.gd-operation-item'); const priority=row && row.querySelector('.gd-sla-priority'); const date=row && row.querySelector('.gd-sla-date');
 if(!date || !date.value){ alert('Choose a project deadline first.'); return; }
 btn.disabled=true;
 const data=new URLSearchParams({action:'gd_client_portal_sla_action',project_id:btn.dataset.projectId,priority:priority ? priority.value : 'normal',due_date:date.value+' 23:59:59',stage_due_date:'',warning_days:'2',_wpnonce:btn.dataset.nonce});
 fetch(window.ajaxurl || (window.location.origin + '/wp-admin/admin-ajax.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()})
 .then(r=>r.json()).then(res=>{ if(res.success){btn.textContent='✓ Saved'; btn.classList.add('is-success'); setTimeout(()=>location.reload(),500);} else {alert((res.data&&res.data.message)||res.data||'SLA update failed.');btn.disabled=false;} })
 .catch(()=>{alert('Unable to save the SLA.');btn.disabled=false;});
});
