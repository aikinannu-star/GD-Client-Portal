(function($){'use strict';
function init(root){var $r=$(root),$i=$r.find('.gdcp-search-input'),$out=$r.find('.gdcp-search-results'),timer;
function hide(){if(!$i.val())$out.hide();}
function render(results){if(!results.length){$out.html('<div class="gdcp-search-empty">No matching records found.</div>').show();return;}var html='';results.forEach(function(x){var tag=x.url?'<a class="gdcp-search-result" href="'+esc(x.url)+'">':'<button type="button" class="gdcp-search-result" data-id="'+x.id+'">';var end=x.url?'</a>':'</button>';html+=tag+'<span class="gdcp-search-type">'+esc(x.type)+'</span><strong>'+esc(x.title)+'</strong><small>'+esc(x.meta)+'</small>'+end;});$out.html(html).show();}
function esc(s){return $('<div>').text(s||'').html();}
$i.on('focus',function(){$r.addClass('gdcp-focused');if($i.val().length>=2)$out.show();}).on('input',function(){clearTimeout(timer);var q=$i.val().trim();if(q.length<2){$out.hide().empty();return;}$out.html('<div class="gdcp-search-loading">Searching…</div>').show();timer=setTimeout(function(){$.post(gdcpUnifiedSearch.ajaxurl,{action:'gd_client_portal_unified_search',nonce:gdcpUnifiedSearch.nonce,q:q}).done(function(resp){render(resp.success&&resp.data?resp.data.results:[]);}).fail(function(){$out.html('<div class="gdcp-search-empty">Search is temporarily unavailable.</div>').show();});},180);});
$(document).on('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();$i.trigger('focus');$i[0].select();}if(e.key==='Escape'){$out.hide();$i.trigger('blur');}});$(document).on('click',function(e){if(!$(e.target).closest($r).length)$out.hide();});$i.on('blur',function(){setTimeout(hide,150);});
}
$(function(){$('.gdcp-unified-search').each(function(){init(this);});});
})(jQuery);
