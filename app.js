'use strict';
const form=document.querySelector('#registration-form');
const error=document.querySelector('#form-error');
let step=1;
function setStep(value){step=value;document.querySelectorAll('[data-step]').forEach(el=>el.hidden=Number(el.dataset.step)!==value);document.querySelectorAll('[data-step-label]').forEach(el=>el.classList.toggle('active',Number(el.dataset.stepLabel)<=value));document.querySelector('.step-count').textContent=`0${value} / 02`;document.querySelector('#submit-label').textContent=value===1?'Continuar cadastro':'Concluir demonstração';document.querySelector('#back-step').hidden=value===1;error.hidden=true;}
function showError(message,field){error.textContent=message;error.hidden=false;if(field){field.setAttribute('aria-invalid','true');field.focus();}return false;}
function digits(value){return value.replace(/\D/g,'');}
function validCPF(value){const d=digits(value);if(d.length!==11||/^(\d)\1{10}$/.test(d))return false;for(let n=9;n<11;n++){let sum=0;for(let i=0;i<n;i++)sum+=Number(d[i])*(n+1-i);let k=(sum*10)%11;if(k===10)k=0;if(k!==Number(d[n]))return false;}return true;}
function validate(){const visible=form.querySelector(`[data-step="${step}"]`);for(const field of visible.querySelectorAll('[required]')){if(!field.checkValidity())return showError(field.type==='checkbox'?'Confirme os itens obrigatórios para continuar.':'Preencha os campos obrigatórios para continuar.',field);}if(step===1){const name=form.elements.name;if(name.value.trim().split(/\s+/).length<2)return showError('Informe nome e sobrenome fictícios para testar.',name);if(!validCPF(form.elements.cpf.value))return showError('Confira o CPF: são 11 dígitos, com verificadores válidos.',form.elements.cpf);const phone=digits(form.elements.phone.value);if(phone.length<10||phone.length>11)return showError('Informe um telefone com DDD.',form.elements.phone);if(!form.elements.email.checkValidity())return showError('Confira o formato do e-mail ou deixe o campo vazio.',form.elements.email);}if(step===2&&!form.elements.receipt.value.trim())return showError('Informe a identificação demonstrativa da compra.',form.elements.receipt);return true;}
form.addEventListener('input',e=>{e.target.removeAttribute('aria-invalid');error.hidden=true;});
document.querySelector('#cpf').addEventListener('input',e=>{let d=digits(e.target.value).slice(0,11);e.target.value=d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');});
document.querySelector('#phone').addEventListener('input',e=>{let d=digits(e.target.value).slice(0,11);e.target.value=d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{4,5})(\d{4})$/,'$1-$2');});
form.addEventListener('submit',e=>{e.preventDefault();if(!validate())return;if(step===1){setStep(2);document.querySelector('#partner').focus();}else{form.reset();form.hidden=true;document.querySelector('.stepper').hidden=true;document.querySelector('.form-intro').hidden=true;document.querySelector('#success').hidden=false;document.querySelector('#success').focus();}});
document.querySelector('#back-step').addEventListener('click',()=>{setStep(1);form.elements.name.focus();});
function restart(){form.reset();form.hidden=false;document.querySelector('.stepper').hidden=false;document.querySelector('.form-intro').hidden=false;document.querySelector('#success').hidden=true;setStep(1);form.elements.name.focus();}
document.querySelector('#restart').addEventListener('click',restart);
document.querySelectorAll('[data-dialog]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.dialog).showModal()));
document.querySelectorAll('dialog').forEach(dialog=>{dialog.querySelector('.close-dialog').addEventListener('click',()=>dialog.close());dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});});
// Motion is progressive enhancement: all content remains readable without JavaScript.
const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
if(!reducedMotion.matches && 'IntersectionObserver' in window){
 document.body.classList.add('motion-ready');
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('in-view');observer.unobserve(entry.target);}}),{threshold:.08});
 document.querySelectorAll('.reveal').forEach((el,index)=>{el.style.setProperty('--reveal-delay',`${el.classList.contains('journey-item')?(index%3)*80:0}ms`);observer.observe(el);});
 document.querySelector('.signup').animate([{opacity:0,transform:'translateY(15px)'},{opacity:1,transform:'translateY(0)'}],{duration:900,easing:'cubic-bezier(.22,1,.36,1)',fill:'both'});
}
// The demo intentionally never persists or transmits registration fields.
window.addEventListener('pagehide',()=>form.reset());
if(document.modelContext?.registerTool){
 try{Promise.resolve(document.modelContext.registerTool({name:'open_campaign_document',title:'Abrir documento da campanha',description:'Abre o regulamento ou o aviso de privacidade no mesmo diálogo da interface. Não envia dados e não realiza cadastro.',inputSchema:{type:'object',properties:{document:{type:'string',enum:['regulamento','privacidade']}},required:['document'],additionalProperties:false},annotations:{readOnlyHint:false,untrustedContentHint:false},execute(input){if(!input||!['regulamento','privacidade'].includes(input.document)||Object.keys(input).some(k=>k!=='document'))throw new Error('Documento inválido. Use regulamento ou privacidade.');const dialog=document.getElementById(input.document);document.querySelectorAll('dialog[open]').forEach(el=>el.close());dialog.showModal();return{document:input.document,visible:dialog.open};}})).catch(()=>{});}catch{}
}
const fillExample=document.querySelector('#fill-example');
fillExample.addEventListener('click',()=>{form.elements.name.value='Pessoa Demonstração';form.elements.cpf.value='529.982.247-25';form.elements.phone.value='(67) 90000-0000';form.elements.city.value='Três Lagoas';form.elements.state.value='MS';form.elements.email.value='demo@example.com';form.elements.partner.value='trok';form.elements.purchaseDate.value='2026-10-08';form.elements.receipt.value='DEMO-001';form.querySelectorAll('[aria-invalid]').forEach(el=>el.removeAttribute('aria-invalid'));error.hidden=true;form.querySelector('button[type="submit"]').focus();});
form.querySelector('button[type="submit"]').disabled=false;
