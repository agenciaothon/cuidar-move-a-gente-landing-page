/* Campaign measurement: categorical events only; never reads input text. */
(() => {
  'use strict';
  if (!['cuidarmoveagente.com.br', 'www.cuidarmoveagente.com.br'].includes(location.hostname)) return;
  // The private panel and audit links use this flag to keep technical previews out of the funnel.
  if (new URLSearchParams(location.search).get('medicao') === 'teste') return;
  const form = document.querySelector('#registration-form');
  if (!form) return;
  const fields = new Set(['name','cpf','phone','email','city','state','partner','adult','terms']);
  const partners = new Set([...document.querySelector('#partner').options].map(option => option.value));
  const sources = new Set(['direto','qr-legado','cartaz','instagram','facebook','whatsapp','parceiro','outro']);
  const key = 'cmg-measurement-v1';
  const ttl = 30 * 60 * 1000;
  const randomId = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2,'0')).join('');
  let stored = null;
  try { stored = JSON.parse(sessionStorage.getItem(key)); } catch (_) {}
  const valid = stored && /^[a-f0-9]{32}$/.test(stored.visit) && Date.now() - stored.touched < ttl;
  const session = valid ? stored : { visit:randomId(), attempt:'', touched:Date.now(), token:'', completed:false };
  let ready = false, lastField = '', lastProgress = 0, queue = Promise.resolve();
  const save = () => { session.touched=Date.now(); try { sessionStorage.setItem(key,JSON.stringify(session)); } catch (_) {} };
  const currentPartner = () => partners.has(form.elements.partner.value) ? form.elements.partner.value : '';
  const context = () => ready && session.attempt ? { visit:session.visit, attempt:session.attempt, token:session.token } : null;
  const payload = (event, details={}) => ({ event, event_id:randomId(), visit:session.visit, attempt:session.attempt, token:session.token,
    field:lastField, partner:currentPartner(), ...details });
  async function send(body) {
    try {
      const controller = typeof AbortController === 'function' ? new AbortController() : null;
      const timer = controller ? setTimeout(() => controller.abort(),5000) : null;
      try {
        const response=await fetch('api/medicao.php',{method:'POST',credentials:'omit',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),signal:controller?.signal,keepalive:true});
        if (!response.ok) return null;
        return await response.json();
      } finally { if (timer) clearTimeout(timer); }
    } catch (_) { return null; }
  }
  function emit(event, details={}) {
    const body=payload(event,details);
    queue=queue.then(async () => { if (!ready) return; body.token=session.token; await send(body); }).catch(() => {});
  }
  const query=new URLSearchParams(location.search);
  let source=(query.get('origem') || query.get('utm_source') || '').toLowerCase();
  if (!source) {
    try { const host=new URL(document.referrer).hostname;
      source=host.includes('instagram.')?'instagram':host.includes('facebook.')?'facebook':host.includes('whatsapp.')?'whatsapp':host==='agenciaothon.github.io'?'qr-legado':'direto';
    } catch (_) { source='direto'; }
  }
  if (!sources.has(source)) source='outro';
  let sourcePartner=query.get('parceiro') || '';
  if (!partners.has(sourcePartner)) sourcePartner='';
  const device=innerWidth<700?'mobile':innerWidth<1100?'tablet':'desktop';
  queue=send({event:'page_view',event_id:randomId(),visit:session.visit,source,partner:sourcePartner,device}).then(data=>{
    if (data?.ok && data.token) { session.token=data.token;ready=true;save(); }
  }).catch(()=>{});
  function start(field) {
    if (!session.attempt || session.completed) { session.attempt=randomId();session.completed=false;emit('form_start',{field}); }
    save();
  }
  form.addEventListener('input',event=>{
    const field=event.target.id;
    if (!fields.has(field)) return;
    start(field);
    if (field!==lastField || Date.now()-lastProgress>15000) {
      lastField=field;lastProgress=Date.now();emit('progress',{field});
    }
  });
  form.addEventListener('change',event=>{
    const field=event.target.id;
    if (!fields.has(field)) return;
    start(field);lastField=field;emit('progress',{field});
  });
  form.addEventListener('submit',()=>{
    // A submit click is an intentional start even when every field is empty.
    start(lastField);emit('submit_attempt');
  },true);
  window.addEventListener('pagehide',()=>{
    save();if (!ready) return;
    const body=JSON.stringify(payload('leave'));
    try {
      if (navigator.sendBeacon) navigator.sendBeacon('api/medicao.php',new Blob([body],{type:'application/json'}));
      else fetch('api/medicao.php',{method:'POST',credentials:'omit',headers:{'Content-Type':'application/json'},body,keepalive:true}).catch(()=>{});
    } catch (_) {}
  });
  window.campaignMetrics={
    context,
    error(code,field='',kind='validation_error') { emit(kind,{code,field:fields.has(field)?field:''}); },
    complete() { session.completed=true;save();emit('success_view',{field:''}); },
    restart() { session.attempt='';session.completed=false;lastField='';save(); }
  };
})();
