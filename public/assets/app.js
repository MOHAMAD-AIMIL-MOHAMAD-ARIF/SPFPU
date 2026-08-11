document.addEventListener('DOMContentLoaded',()=>{
  const nav=document.querySelector('#primary-nav');
  document.querySelector('[data-nav-toggle]')?.addEventListener('click',e=>{const open=nav.classList.toggle('open');e.currentTarget.setAttribute('aria-expanded',String(open));});
  document.querySelectorAll('[data-dialog-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.dialogOpen)?.showModal()));
  document.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
  document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();}));
  document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm))e.preventDefault();}));
  document.querySelector('[data-filter-toggle]')?.addEventListener('click',()=>document.querySelector('[data-filter-fields]')?.classList.toggle('open'));
});
