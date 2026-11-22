(() => {
  const portfolio=document.querySelector('[data-portfolio]');
  if(portfolio){
    const filters=portfolio.querySelector('[data-project-filters]'),cards=[...portfolio.querySelectorAll('[data-project-card]')];
    if(filters){filters.hidden=false;filters.addEventListener('click',event=>{
      const button=event.target.closest('[data-project-filter]');if(!button)return;
      const category=button.dataset.projectFilter;let visible=0;
      cards.forEach(card=>{card.hidden=category!=='all'&&card.dataset.category!==category;if(!card.hidden)visible++;});
      filters.querySelectorAll('button').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));
      const feature=portfolio.querySelector('[data-project-feature]');if(feature)feature.hidden=category!=='all';
      portfolio.querySelector('[data-project-count]').textContent=visible+' réalisation'+(visible>1?'s':'');
    });}
  }
  const dialog=document.querySelector('.project-lightbox'),photos=[...document.querySelectorAll('[data-project-photo]')];
  if(!dialog||!photos.length||typeof dialog.showModal!=='function')return;
  let index=0,opener=null;
  const show=()=>{const photo=photos[index],img=dialog.querySelector('[data-photo-large]');img.src=photo.href;img.alt=photo.dataset.caption;dialog.querySelector('[data-photo-caption]').textContent=photo.dataset.caption;dialog.querySelector('[data-photo-count]').textContent=`${index+1} / ${photos.length}`;};
  photos.forEach((photo,i)=>photo.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();index=i;opener=photo;show();dialog.showModal();dialog.querySelector('[data-photo-close]').focus();}));
  dialog.querySelector('[data-photo-close]').addEventListener('click',()=>dialog.close());
  const change=direction=>{index=(index+direction+photos.length)%photos.length;show();};
  dialog.querySelector('[data-photo-prev]').addEventListener('click',()=>change(-1));dialog.querySelector('[data-photo-next]').addEventListener('click',()=>change(1));
  if(photos.length===1)dialog.querySelector('.project-lightbox-nav').hidden=true;
  dialog.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();change(event.key==='ArrowLeft'?-1:1);}});
  dialog.addEventListener('click',event=>{if(event.target===dialog){const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();}});
  dialog.addEventListener('close',()=>opener?.focus());
})();
