const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),{webcrypto}=require('node:crypto');
const source=fs.readFileSync(require('node:path').join(__dirname,'../tracking.js'),'utf8');
let checks=0;
async function run({fail=false,query='',storageBlocked=false}={}) {
  const requests=[],beacons=[],listeners={},windowListeners={},storage=new Map();
  const form={elements:{partner:{value:'trok'}},addEventListener:(name,fn)=>{(listeners[name]??=[]).push(fn);}};
  const window={addEventListener:(name,fn)=>windowListeners[name]=fn};
  const sandbox={window,location:{hostname:'cuidarmoveagente.com.br',search:query},document:{querySelector:selector=>selector==='#registration-form'?form:{options:[{value:''},{value:'trok'}]},referrer:'https://private.example/path?email=SECRET_INPUT'},URL,URLSearchParams,crypto:webcrypto,Uint8Array,Date,Set,Promise,JSON,Array,Blob,AbortController,setTimeout,clearTimeout,innerWidth:390,
    sessionStorage:{getItem:key=>{if(storageBlocked)throw Error('blocked');return storage.get(key)||null;},setItem:(key,value)=>{if(storageBlocked)throw Error('blocked');storage.set(key,value);}},
    navigator:{sendBeacon:(url,body)=>{beacons.push(body);return true;}},fetch:async(url,options)=>{if(fail)throw Error('offline');requests.push(JSON.parse(options.body));return{ok:true,json:async()=>({ok:true,token:'test-token'})};}};
  vm.runInNewContext(source,sandbox);
  const settle=async()=>{for(let i=0;i<30;i++)await Promise.resolve();};
  await settle();
  const dispatch=(name,id)=>{for(const fn of listeners[name]||[])fn({target:{id,value:'SECRET_INPUT'}});};
  return{requests,beacons,window,dispatch,settle,windowListeners};
}
(async()=>{
  const t=await run({query:'?origem=cartaz&parceiro=trok&email=SECRET_INPUT'});
  assert.equal(t.requests.length,1);assert.equal(t.requests[0].event,'page_view');checks++;
  t.dispatch('input','name');t.dispatch('input','name');t.dispatch('input','cpf');await t.settle();
  assert.equal(t.requests.filter(r=>r.event==='form_start').length,1);checks++;
  assert.equal(JSON.stringify(t.requests).includes('SECRET_INPUT'),false);checks++;
  const first=t.window.campaignMetrics.context();assert.ok(first.visit && first.attempt && first.token);checks++;
  t.window.campaignMetrics.error('required','email');await t.settle();assert.ok(t.requests.some(r=>r.event==='validation_error'&&r.field==='email'));checks++;
  t.window.campaignMetrics.complete();await t.settle();assert.ok(t.requests.some(r=>r.event==='success_view'));checks++;
  t.window.campaignMetrics.restart();t.dispatch('change','partner');await t.settle();
  assert.notEqual(t.window.campaignMetrics.context().attempt,first.attempt);assert.equal(t.window.campaignMetrics.context().visit,first.visit);checks++;
  t.windowListeners.pagehide();assert.equal(t.beacons.length,1);checks++;
  const blocked=await run({storageBlocked:true});blocked.dispatch('input','name');await blocked.settle();assert.ok(blocked.requests.some(r=>r.event==='form_start'));checks++;
  const offline=await run({fail:true});offline.dispatch('input','name');offline.window.campaignMetrics.error('network','','request_error');await offline.settle();assert.equal(offline.window.campaignMetrics.context(),null);checks++;
  const audit=await run({query:'?medicao=teste'});assert.equal(audit.requests.length,0);checks++;
  const badSource=await run({query:'?utm_source=SECRET_INPUT'});assert.equal(badSource.requests[0].source,'outro');assert.equal(JSON.stringify(badSource.requests).includes('SECRET_INPUT'),false);checks++;
  console.log(`Passed ${checks} browser-collector checks; no browser or production database used.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
