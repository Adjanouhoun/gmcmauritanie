(() => {
 const header=document.querySelector('[data-site-header]');if(!header)return;
 const nav=header.querySelector('nav'),toggle=header.querySelector('.gmc-menu-toggle'),mobile=matchMedia('(max-width:991px)'),buttons=[...header.querySelectorAll('.gmc-submenu-toggle')];
 const closeSubs=()=>buttons.forEach(b=>{b.setAttribute('aria-expanded','false');document.getElementById(b.getAttribute('aria-controls')).hidden=true;});
 const setOpen=open=>{toggle.setAttribute('aria-expanded',String(open));toggle.querySelector('[data-menu-label]').textContent=open?'Fermer':'Menu';toggle.querySelector('[data-menu-symbol]').textContent=open?'×':'☰';nav.hidden=mobile.matches&&!open;};
 header.classList.add('is-enhanced');toggle.hidden=false;buttons.forEach(b=>{b.hidden=false;b.addEventListener('click',()=>{const open=b.getAttribute('aria-expanded')!=='true';closeSubs();b.setAttribute('aria-expanded',String(open));document.getElementById(b.getAttribute('aria-controls')).hidden=!open;});});
 const reset=()=>{closeSubs();setOpen(false);};reset();mobile.addEventListener('change',reset);
 toggle.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';closeSubs();setOpen(open);});
 header.addEventListener('keydown',e=>{if(e.key==='Escape'){const sub=buttons.find(b=>b.getAttribute('aria-expanded')==='true');if(sub){closeSubs();sub.focus();}else if(mobile.matches&&toggle.getAttribute('aria-expanded')==='true'){setOpen(false);toggle.focus();}e.preventDefault();}});
 header.addEventListener('focusout',e=>{if(e.relatedTarget&&!header.contains(e.relatedTarget)){closeSubs();setOpen(false);}});
 document.addEventListener('click',e=>{if(!header.contains(e.target)){closeSubs();setOpen(false);}});
})();
