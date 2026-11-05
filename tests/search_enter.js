const fs=require('fs'), vm=require('vm'), assert=require('assert');
function node(value='') {
  return {value,hidden:false,innerHTML:'',events:{},attrs:{},
    addEventListener(type,fn){(this.events[type] ||= []).push(fn);},
    fire(type,event={}){(this.events[type]||[]).forEach(fn=>fn(event));},
    setAttribute(k,v){this.attrs[k]=v;},getAttribute(k){return this.attrs[k]||'';},removeAttribute(k){delete this.attrs[k];},
    querySelectorAll(){return [];},hasAttribute(){return true;}
  };
}
const fields={dept:node('BSCS'),type:node('thesis'),year:node('2025-2026'),from:node('2024-2025'),to:node('2026-2027'),sort:node('newest'),apply:node(),reset:node(),hits:node(),count:node(),page:node()};
const bar=node();bar.querySelector=selector=>fields[selector.slice(11,-1)]||null;
const q=node('reporter');q.closest=()=>bar;
const nodes={q,results:node(),'dept-paper-cards':node(),'dept-paper-pager':node(),'dept-paper-empty':node(),suggestions:node()};
const events={};const calls=[];
const document={getElementById:id=>nodes[id]||null,querySelector:()=>null,querySelectorAll:()=>[],
  addEventListener:(type,fn)=>{events[type]=fn;},
  createElement(){const el=node();Object.defineProperty(el,'textContent',{set(value){el.innerHTML=String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}});return el;}
};
const context={document,window:{location:{search:''}},URLSearchParams,setTimeout,clearTimeout,
  fetch(url){return new Promise(resolve=>calls.push({url,resolve}));}
};
vm.runInNewContext(fs.readFileSync(__dirname+'/../public/assets/js/search.js','utf8'),context);
events.DOMContentLoaded();
const flush=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 q.fire('input');assert.strictEqual(calls.length,0,'Typing in dashboard only suggests titles');
 q.fire('keydown',{key:'Enter',preventDefault(){}});assert.strictEqual(calls.length,1);
 const params=new URLSearchParams(calls[0].url.split('?')[1]);
 assert.strictEqual(params.get('full_text'),'1');assert.strictEqual(params.get('program_code'),'BSCS');assert.strictEqual(params.get('school_year_from'),'2024-2025');
 assert.strictEqual(nodes['dept-paper-cards'].hidden,true);
 calls[0].resolve({ok:true,json:()=>Promise.resolve({results:[{id:1,title:'Artificial intelligence',authors:'Jane Doe',program_code:'BSCS',search_text:'The reporter <img src=x> studies the system.',full_access:false}],featured:false})});
 await flush();await flush();
 assert(nodes.results.innerHTML.includes('<mark class="search-term">reporter</mark>'));
 assert(nodes.results.innerHTML.includes('&lt;img src=x&gt;'));assert(!nodes.results.innerHTML.includes('<img src=x>'));
 q.value='other';q.fire('input');assert.strictEqual(nodes['dept-paper-cards'].hidden,false);assert.strictEqual(nodes.results.innerHTML,'');
 q.fire('keydown',{key:'Enter',preventDefault(){}});fields.reset.fire('click');
 calls[1].resolve({ok:true,json:()=>Promise.resolve({results:[],featured:false})});await flush();await flush();
 assert.strictEqual(nodes.results.innerHTML,'','Stale response cannot restore cleared results');
 q.value='reporter';q.fire('keydown',{key:'Enter',preventDefault(){}});
 calls[2].resolve({ok:false});await flush();await flush();
 assert(nodes.results.innerHTML.includes('Press Enter to try again'),'Request failure has a retry message');
 console.log('Enter-search workflow passed: title suggestions, broader search, filters, highlighted/escaped snippets, reset and stale-response protection.');
})().catch(error=>{console.error(error);process.exitCode=1;});
