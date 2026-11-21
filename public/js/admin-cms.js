(() => {
  const query=document.querySelector('[data-filter-list]'), status=document.querySelector('[data-status-filter]');
  const filter=()=>document.querySelectorAll('[data-list-item]').forEach(row=>{row.hidden=!(row.textContent.toLocaleLowerCase().includes((query?.value||'').toLocaleLowerCase())&&(!status?.value||row.dataset.status===status.value));});
  query?.addEventListener('input',filter);status?.addEventListener('change',filter);
  document.querySelectorAll('.rich-toolbar button').forEach(button=>{
    button.addEventListener('mousedown',event=>event.preventDefault());
    button.addEventListener('click',()=>{
      const editor=button.closest('.field-group').querySelector('.rich-editor');editor.focus();
      const command=button.dataset.format;
      if(command==='h3')document.execCommand('formatBlock',false,'h3');
      else if(command==='createLink'){const url=window.prompt('Lien interne (/contact) ou adresse https://');if(url&&(/^(https?:\/\/|\/(?!\/))/.test(url))&&!/[\s\\]/.test(url))document.execCommand('createLink',false,url);}
      else document.execCommand(command,false,null);
    });
  });
  document.querySelectorAll('.rich-editor').forEach(editor=>{
    editor.addEventListener('paste',event=>{event.preventDefault();document.execCommand('insertText',false,event.clipboardData.getData('text/plain'));});
    editor.addEventListener('drop',event=>event.preventDefault());
  });
  document.querySelector('.cms-editor-form')?.addEventListener('submit',()=>document.querySelectorAll('.rich-editor').forEach(editor=>{editor.closest('.field-group').querySelector('.rich-source').value=editor.innerHTML;}));
  const updateImages=()=>{
    document.querySelectorAll('[data-media-field][data-kind="image"]').forEach(field=>{
      const img=field.querySelector('.media-selection img'), warning=field.querySelector('[data-image-warning]');if(!warning)return;
      const w=Number(img?.dataset.width),h=Number(img?.dataset.height);
      const sizes={slide:[1920,700],slide_mobile:[780,940],logo:[220,160],icon:[128,128],photo:[768,400]};
      const [mw,mh]=sizes[field.dataset.usage]||sizes.photo;
      warning.hidden=!w||!h||(w>=mw&&h>=mh);
      warning.textContent=`Image de ${w} × ${h} px : résolution faible pour cet usage. Recommandation : au moins ${mw} × ${mh} px. L’image reste utilisable, mais peut paraître floue.`;
    });
    const main=document.querySelector('[data-key="image"] .media-selection img'),mobile=document.querySelector('[data-key="mobile"] .media-selection img');
    document.querySelectorAll('[data-crop]').forEach(preview=>{
      const isMobile=preview.dataset.crop==='mobile',source=isMobile?(mobile||main):main;
      if(source)preview.src=source.dataset.original||source.src;else preview.removeAttribute('src');
      const prefix=isMobile?'mobileFocus':'focus';
      const x=document.querySelector(`[data-focus="${prefix}X"]`)?.value??50,y=document.querySelector(`[data-focus="${prefix}Y"]`)?.value??50;
      preview.style.objectPosition=`${x}% ${y}%`;
    });
    document.querySelectorAll('[data-focus]').forEach(input=>{input.nextElementSibling.value=input.value+' %';});
  };
  document.querySelectorAll('[data-focus]').forEach(input=>input.addEventListener('input',updateImages));
  updateImages();
  const dialog=document.querySelector('#media-picker');let target=null, maxBytes=null, uploading=false;
  const pickerStatus=document.querySelector('[data-picker-status]');
  const notice=(message,error=false)=>{if(!pickerStatus)return;pickerStatus.textContent=message;pickerStatus.className='alert '+(error?'alert-danger':'alert-info')+(message?'':' d-none');};
  const mediaFilter=()=>{const term=(document.querySelector('[data-media-search]')?.value||'').toLocaleLowerCase();document.querySelectorAll('[data-media-id]').forEach(option=>{option.hidden=option.dataset.mediaKind!==(target?.dataset.kind==='document'?'document':'image')||!option.dataset.mediaName.toLocaleLowerCase().includes(term);});};
  const readJson=async response=>{let data;try{data=await response.json();}catch{throw new Error('La réponse du serveur est indisponible. Réessayez après avoir vérifié votre connexion.');}if(!response.ok)throw new Error(data.error||'Impossible de terminer cette opération.');return data;};
  const mediaOption=item=>{
    const option=document.createElement('button');option.type='button';option.className='media-option';
    Object.assign(option.dataset,{mediaId:String(item.id),mediaKind:item.kind,mediaUrl:item.url,mediaName:item.name,mediaWidth:item.width||0,mediaHeight:item.height||0});
    if(item.kind==='image'){const img=document.createElement('img');img.src=item.thumbnail||item.url;img.dataset.original=item.url;img.dataset.width=item.width||0;img.dataset.height=item.height||0;img.alt=item.alt||item.name;img.loading='lazy';option.append(img);}else{const pdf=document.createElement('span');pdf.className='pdf-icon';pdf.textContent='PDF';option.append(pdf);}
    const label=document.createElement('span');label.textContent=item.name+(item.width?' · '+item.width+' × '+item.height+' px':'');option.append(label);return option;
  };
  const refresh=async()=>{
    const data=await readJson(await fetch(dialog.dataset.catalogueUrl,{headers:{Accept:'application/json'},cache:'no-store'}));
    maxBytes=data.maxBytes;document.querySelector('[data-upload-limit]').textContent='JPG, PNG, WebP ou PDF · '+(Math.floor(maxBytes/104857.6)/10).toLocaleString('fr-FR')+' Mo maximum.';
    document.querySelector('.media-picker-grid').replaceChildren(...data.items.map(mediaOption));mediaFilter();
  };
  const selectMedia=item=>{
    if(!target)return;const selection=target.querySelector('.media-selection');const multi=target.dataset.kind==='gallery';
    if(multi&&Array.from(selection.querySelectorAll('input')).some(input=>input.value===String(item.id))){dialog.close();return;}
    if(!multi)selection.replaceChildren();
    const card=document.createElement('div');card.className='media-chosen';const input=document.createElement('input');input.type='hidden';input.name=target.dataset.inputName+(multi?'[]':'');input.value=item.id;card.append(input);
    if(item.kind==='image'){const img=document.createElement('img');img.src=item.thumbnail||item.url;img.dataset.original=item.url;img.dataset.width=item.width||0;img.dataset.height=item.height||0;img.alt=item.alt||item.name;card.append(img);}else{const label=document.createElement('span');label.textContent=item.name+(item.width?' · '+item.width+' × '+item.height+' px':'');card.append(label);}
    if(multi){const remove=document.createElement('button');remove.type='button';remove.textContent='Retirer';remove.className='btn btn-sm btn-light';remove.dataset.removeMedia='';card.append(remove);}
    selection.append(card);updateImages();dialog.close();
  };
  document.querySelectorAll('[data-pick-media]').forEach(button=>button.addEventListener('click',async()=>{
    target=button.closest('[data-media-field]');target.dataset.inputName=button.dataset.name;document.querySelector('[data-media-search]').value='';notice('');mediaFilter();dialog.showModal();
    try{await refresh();}catch(error){notice(error.message,true);}
  }));
  document.querySelector('[data-close-picker]')?.addEventListener('click',()=>{if(!uploading)dialog.close();});
  dialog?.addEventListener('cancel',event=>{if(uploading)event.preventDefault();});
  document.querySelector('[data-media-search]')?.addEventListener('input',mediaFilter);
  document.querySelector('.media-picker-grid')?.addEventListener('click',event=>{
    const option=event.target.closest('[data-media-id]');if(!option||uploading)return;
    selectMedia({id:option.dataset.mediaId,name:option.dataset.mediaName,kind:option.dataset.mediaKind,url:option.dataset.mediaUrl,width:Number(option.dataset.mediaWidth),height:Number(option.dataset.mediaHeight)});
  });
  document.querySelector('[data-inline-upload]')?.addEventListener('submit',async event=>{
    event.preventDefault();if(uploading)return;const form=event.currentTarget;const file=form.querySelector('[name="file"]').files[0];
    if(!file)return;if(maxBytes&&file.size>maxBytes){notice('Le fichier dépasse la limite autorisée. Choisissez un fichier plus petit.',true);return;}
    uploading=true;const button=form.querySelector('[type="submit"]');button.disabled=true;form.setAttribute('aria-busy','true');notice('Import du fichier en cours…');
    try{
      const data=await readJson(await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}}));
      document.querySelector('.media-picker-grid').prepend(mediaOption(data.item));mediaFilter();form.reset();
      if(data.item.kind===(target.dataset.kind==='document'?'document':'image'))selectMedia(data.item);
      else notice('Média importé. Il sera disponible dans le champ adapté (image ou document PDF).');
    }catch(error){notice(error.message,true);}finally{uploading=false;button.disabled=false;form.removeAttribute('aria-busy');}
  });
  document.querySelectorAll('[data-clear-media]').forEach(button=>button.addEventListener('click',()=>{const selection=button.closest('[data-media-field]').querySelector('.media-selection');selection.querySelectorAll('input').forEach(input=>{input.value='';});selection.querySelectorAll('.media-chosen').forEach(card=>{card.querySelectorAll('img,span').forEach(el=>el.remove());});updateImages();}));
  document.addEventListener('click',event=>{const button=event.target.closest('[data-remove-media]');if(button)button.closest('.media-chosen').remove();});
})();
