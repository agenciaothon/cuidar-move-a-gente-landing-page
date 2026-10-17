'use strict';
const $ = selector => document.querySelector(selector);
const number = value => Number(value || 0).toLocaleString('pt-BR');
const pct = (part,total) => total ? `${(100*part/total).toFixed(1).replace('.',',')}%` : '—';
const partners = {trok:'Trok Car Brasil','pizzaria-da-mama':'Pizzaria da Mama','brutus-ms':'Brutus MS','best-life':'Best Life','apollo-pet-salon':'Apollo Pet Salon','mais-tintas':'Mais Tintas','geracao-store':'Geração Store','alvorada-veiculos':'Alvorada Veículos',beone:'beOne','jessica-walter':'Jéssica Walter',frigodil:'Frigodil',tricarne:'Tricarne',ccaa:'CCAA',concept:'Concept',virtus:'Virtus — Espaço Católico'};
const fields = {'':'Etapa não identificada',name:'Nome',cpf:'CPF',phone:'Celular',email:'E-mail',city:'Cidade',state:'UF',partner:'Estabelecimento',adult:'Declaração de participação',terms:'Regulamento e privacidade'};
const stages = {dados:'Dados',estabelecimento:'Estabelecimento',declaracoes:'Declarações',envio:'Envio',confirmacao:'Confirmação'};
const sources = {direto:'Direto / não identificado','qr-legado':'QR dos cartazes antigos',cartaz:'Cartaz / display',instagram:'Instagram',facebook:'Facebook',whatsapp:'WhatsApp',parceiro:'Link de parceiro',outro:'Outra origem'};
const codes = {required:'Campo obrigatório pendente',name_invalid:'Nome incompleto',cpf_invalid:'CPF inválido',phone_invalid:'Telefone inválido',availability_failed:'Cadastro indisponível ao abrir',session_expired:'Sessão expirada',timeout:'Tempo de resposta excedido',network:'Falha de conexão',invalid_response:'Resposta inesperada',server_rejected:'Envio recusado pelo servidor',rate_limited:'Limite de envios',service_unavailable:'Serviço indisponível',campaign_closed:'Fora do período da campanha',unknown:'Outro erro'};
function element(tag,text,cls) { const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node; }
function table(target,headers,rows,empty='Sem registros no período.') {
  const root=$(target);root.replaceChildren();
  if (!rows.length) {root.append(element('p',empty,'empty'));return;}
  const table=element('table'),head=element('thead'),tr=element('tr');
  headers.forEach(value=>tr.append(element('th',value)));head.append(tr);table.append(head);
  const body=element('tbody');rows.forEach(values=>{const row=element('tr');values.forEach(value=>row.append(element('td',String(value))));body.append(row);});table.append(body);root.append(table);
}
function bar(target,label,count,total,detail='') {
  const row=element('div',undefined,'stat-row'),top=element('div',undefined,'stat-top');top.append(element('span',label),element('strong',number(count)));
  const track=element('div',undefined,'bar'),fill=element('i');fill.style.width=`${total?Math.min(100,Math.max(0,100*count/total)):0}%`;track.append(fill);
  row.append(top,track);if(detail)row.append(element('small',detail));target.append(row);
}
function dayLabel(value) {const [y,m,d]=value.split('-');return `${d}/${m}`;}
function dailyRows(report) {
  const days=new Map();
  const measurementDay=report.measurement_started_at?new Date(new Date(report.measurement_started_at.replace(' ','T')+'Z').getTime()-4*3600000).toISOString().slice(0,10):'9999-12-31';
  const get=day=>{if(!days.has(day)){const empty=day<measurementDay?null:0;days.set(day,{day,visits:empty,started:empty,confirmed:empty,abandoned:empty,registrations:0});}return days.get(day);};
  const last=report.to<report.generated_at.slice(0,10)?report.to:report.generated_at.slice(0,10);
  for(let date=new Date(report.from+'T12:00:00Z');date.toISOString().slice(0,10)<=last;date.setUTCDate(date.getUTCDate()+1))get(date.toISOString().slice(0,10));
  report.daily_visits.forEach(row=>Object.assign(get(row.day),row));report.daily_forms.forEach(row=>Object.assign(get(row.day),row));report.daily_registrations.forEach(row=>Object.assign(get(row.day),row));
  return [...days.values()].sort((a,b)=>a.day.localeCompare(b.day));
}
function chart(rows) {
  const root=$('#chart');root.replaceChildren();
  if (!rows.length) {root.append(element('p','Os primeiros indicadores aparecerão após os acessos à campanha.','empty'));return;}
  const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 960 230');svg.setAttribute('role','img');svg.setAttribute('aria-label','Sessões e cadastros por dia');
  const title=document.createElementNS(ns,'title');title.textContent='Sessões e cadastros por dia';svg.append(title);
  const max=Math.max(1,...rows.flatMap(row=>[Number(row.visits),Number(row.registrations)]));
  [0,.5,1].forEach(ratio=>{const line=document.createElementNS(ns,'line');line.setAttribute('x1',40);line.setAttribute('x2',945);line.setAttribute('y1',190-ratio*160);line.setAttribute('y2',190-ratio*160);line.setAttribute('stroke','#eee2e3');svg.append(line);const text=document.createElementNS(ns,'text');text.setAttribute('x',5);text.setAttribute('y',194-ratio*160);text.textContent=number(Math.round(max*ratio));svg.append(text);});
  const slot=900/rows.length;
  rows.forEach((row,i)=>{const x=45+slot*i,w=Math.min(26,slot*.32);[['visits','#ef7c91'],['registrations','#713649']].forEach(([key,color],j)=>{const rect=document.createElementNS(ns,'rect'),height=160*Number(row[key])/max;rect.setAttribute('x',x+slot/2+(j===0?-w-1:1));rect.setAttribute('y',190-height);rect.setAttribute('width',w);rect.setAttribute('height',height);rect.setAttribute('rx',3);rect.setAttribute('fill',color);const title=document.createElementNS(ns,'title');title.textContent=`${row.day}: ${key==='visits'?'sessões':'cadastros'} ${row[key]}`;rect.append(title);svg.append(rect);});if(i%Math.max(1,Math.ceil(rows.length/12))===0){const text=document.createElementNS(ns,'text');text.setAttribute('x',x+slot/2);text.setAttribute('y',216);text.setAttribute('text-anchor','middle');text.textContent=dayLabel(row.day);svg.append(text);}});
  root.append(svg,element('p','● Rosa: sessões medidas  ·  ● Vinho: cadastros gravados no banco','caption'));
}
function render(report) {
  const s=report.summary;
  ['registrations','visits','started','abandoned'].forEach(key=>$('#kpi-'+key).textContent=number(s[key]));
  const startedAt=report.measurement_started_at?new Date(report.measurement_started_at.replace(' ','T')+'Z').toLocaleString('pt-BR',{timeZone:'America/Campo_Grande'}):'a partir do primeiro acesso';
  $('#coverage').textContent=`Medição de navegação desde ${startedAt}. Os ${number(report.baseline_registrations)} cadastros anteriores continuam preservados no banco. Não há histórico de visitas anterior à ativação.`;
  const funnel=$('#funnel');funnel.replaceChildren();
  [['Acessaram a campanha',s.visits],['Começaram a preencher',s.visits_started],['Tentaram enviar',s.visits_submitted],['Receberam confirmação do servidor',s.visits_confirmed]].forEach(([label,count])=>bar(funnel,label,count,s.visits,`${pct(count,s.visits)} das sessões`));
  const status=$('#attempt-status');status.replaceChildren();
  [['Confirmadas',s.confirmed,'Inclui reenvios válidos de CPF já participante; não cria chances extras.'],['Em andamento',s.active,'Atividade nos últimos 30 minutos, sem conclusão.'],['Abandonadas',s.abandoned,'Sem atividade há 30 minutos e sem confirmação no servidor.']].forEach(([label,count,detail])=>bar(status,label,count,s.started,detail));
  status.append(element('p',`Abandono entre tentativas já encerradas: ${pct(s.abandoned,s.confirmed+s.abandoned)}. Cadastros novos atribuídos às tentativas medidas: ${number(s.new_tracked)}.`,'caption'));
  const shown=value=>value===null?'—':number(value);
  const days=dailyRows(report);chart(days);table('#daily-table',['Dia','Sessões','Inícios','Confirmadas','Desistências','Cadastros no banco'],days.map(row=>[dayLabel(row.day),shown(row.visits),shown(row.started),shown(row.confirmed),shown(row.abandoned),number(row.registrations)]));
  table('#fields-table',['Último campo / etapa','Desistências'],report.abandonment_fields.map(row=>[`${fields[row.last_field]||'Outro'} · ${stages[row.last_stage]||'Dados'}`,number(row.abandoned)]));
  table('#errors-table',['Dificuldade','Campo','Ocorrências'],report.errors.map(row=>[codes[row.code]||'Outro erro',fields[row.field]||'Sem campo específico',number(row.total)]));
  table('#sources-table',['Origem','Sessões','Inícios','Confirmadas'],report.sources.map(row=>[`${sources[row.source]||'Outra origem'}${row.source_partner?' · '+(partners[row.source_partner]||'Parceiro'):''}`,number(row.visits),number(row.started),number(row.confirmed)]));
  const counts=Object.fromEntries(report.partners.map(row=>[row.partner,Number(row.registrations)]));table('#partners-table',['Estabelecimento','Cadastros'],Object.entries(partners).map(([key,label])=>[label,counts[key]||0]).sort((a,b)=>b[1]-a[1]));
  $('#devices').replaceChildren(...report.devices.map(row=>element('span',`${({mobile:'Celular',tablet:'Tablet',desktop:'Computador'})[row.device]||row.device}: ${number(row.visits)}`,'chip')));
  $('#updated-at').textContent='Atualizado em '+new Date(report.generated_at).toLocaleString('pt-BR',{timeZone:'America/Campo_Grande'})+' · horário de Três Lagoas/MS.';
}
if(document.body.dataset.authorized!=='true') {
  const hash=new URLSearchParams(location.hash.slice(1)),input=$('#activation-key');
  const key=hash.get('chave')||input?.value;
  if(key && /^[a-f0-9]{64}$/.test(key)) {input.value=key;$('#login-form').hidden=true;$('#activation-form').hidden=false;history.replaceState(null,'',location.pathname);}
} else {
  let loading=false, lastSuccess=0;
  function params(){return new URLSearchParams({from:$('#from').value,to:$('#to').value});}
  async function load(){
    if(loading)return;loading=true;$('#apply').disabled=true;
    try {const response=await fetch('./?format=json&'+params(),{cache:'no-store',credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.message||'Não foi possível atualizar.');render(data);lastSuccess=Date.now();$('#panel-error').hidden=true;}
    catch(error){$('#panel-error').textContent=error.message+(lastSuccess?' Os números exibidos são da última atualização bem-sucedida.':'');$('#panel-error').hidden=false;}
    finally{loading=false;$('#apply').disabled=false;}
  }
  $('#apply').addEventListener('click',load);$('#export').addEventListener('click',()=>location.assign('./?format=csv&'+params()));$('#print').addEventListener('click',()=>window.print());
  setInterval(()=>{if(!document.hidden)load();},60000);load();
  Object.entries(partners).forEach(([key,label])=>{const option=element('option',label);option.value=key;$('#link-partner').append(option);});
  function build(){const url=new URL('https://cuidarmoveagente.com.br/');url.searchParams.set('origem',$('#link-source').value);if($('#link-partner').value)url.searchParams.set('parceiro',$('#link-partner').value);url.hash='cadastro';$('#generated-link').value=url.href;}
  $('#link-source').addEventListener('change',build);$('#link-partner').addEventListener('change',build);build();
  $('#copy-link').addEventListener('click',async()=>{try{await navigator.clipboard.writeText($('#generated-link').value);$('#copy-link').textContent='Link copiado ✓';}catch(_){$('#generated-link').select();$('#copy-link').textContent='Selecione e copie o link acima';}});
}
