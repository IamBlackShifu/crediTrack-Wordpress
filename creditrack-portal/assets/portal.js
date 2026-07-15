document.addEventListener('DOMContentLoaded',()=>{
  const menu=document.querySelector('.ct-mobile-menu');
  const sidebar=document.querySelector('.ct-sidebar');
  if(menu&&sidebar){
    menu.setAttribute('aria-expanded','false');
    menu.addEventListener('click',()=>{const open=sidebar.classList.toggle('open');menu.setAttribute('aria-expanded',String(open));});
    sidebar.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{sidebar.classList.remove('open');menu.setAttribute('aria-expanded','false');}));
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){sidebar.classList.remove('open');menu.setAttribute('aria-expanded','false');menu.focus();}});
  }
  const profileTrigger=document.querySelector('.ct-profile-trigger');
  const profileDropdown=document.querySelector('.ct-profile-dropdown');
  if(profileTrigger&&profileDropdown){
    const closeProfile=()=>{profileDropdown.hidden=true;profileTrigger.setAttribute('aria-expanded','false');};
    profileTrigger.addEventListener('click',event=>{event.stopPropagation();const opening=profileDropdown.hidden;profileDropdown.hidden=!opening;profileTrigger.setAttribute('aria-expanded',String(opening));});
    document.addEventListener('click',event=>{if(!profileDropdown.contains(event.target)&&event.target!==profileTrigger)closeProfile();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!profileDropdown.hidden){closeProfile();profileTrigger.focus();}});
  }
  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type=submit]');if(button){button.disabled=true;button.dataset.label=button.textContent;button.textContent='Working…';}}));
  document.querySelectorAll('[data-confirm]').forEach(element=>element.addEventListener('click',event=>{if(!window.confirm(element.dataset.confirm))event.preventDefault();}));
  const preview=document.querySelector('[data-loan-preview]');
  const loanForm=document.querySelector('#ct-loan-form');
  if(preview&&loanForm){
    const update=()=>{const principal=Number(loanForm.principal.value||0),rate=Number(loanForm.interest_rate.value||0),months=Number(loanForm.term_months.value||0),type=loanForm.interest_type.value;let interest=0,total=principal,payment=0;if(type==='Amortized'&&months){const monthly=rate/1200;payment=monthly?principal*monthly/(1-Math.pow(1+monthly,-months)):principal/months;total=payment*months;interest=total-principal;}else{const factor=type==='Monthly'?months:type==='Quarterly'?months/3:type==='Annual'?months/12:1;interest=principal*rate/100*factor;total=principal+interest;payment=months?total/months:0;}preview.textContent=`Estimated interest: ${interest.toFixed(2)} · Total: ${total.toFixed(2)} · Installment: ${payment.toFixed(2)}`;};
    loanForm.addEventListener('input',update);update();
  }
});
