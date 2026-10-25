import {chromium} from '../../mcp-server/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const fixture=JSON.parse(fs.readFileSync('/tmp/wpgptvibe-review-test.json','utf8'));
const token=JSON.parse(fs.readFileSync('/tmp/wpgptvibe-test-identity.json','utf8')).access_token;
const base='https://127.0.0.1:8090';const browser=await chromium.launch({headless:true});const page=await browser.newPage({ignoreHTTPSErrors:true});
try{
 const unsafe=await page.request.get(fixture.unsafe_preview_url);assert.equal(unsafe.status(),403);assert.match(await unsafe.text(),/staging/);
 console.log('PASS: previously valid theme preview token refuses changed executable files before theme load');
 await page.goto(base+'/wp-login.php');await page.request.post(base+'/wp-login.php',{form:{log:'admin',pwd:'test-only-password','wp-submit':'Log In',redirect_to:base+'/wp-admin/',testcookie:'1'}});
 await page.goto(base+'/wp-admin/tools.php?page=wpgptvibe');
 await page.getByRole('heading',{name:'Pages for Review',exact:true}).waitFor();await page.getByRole('link',{name:'Review in editor',exact:true}).first().waitFor();
 await page.goto(base+'/wp-admin/post.php?post='+fixture.draft_id+'&action=edit');
 await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostId()>0);
 await page.getByRole('button',{name:'Close',exact:true}).first().click({timeout:2000}).catch(()=>{});
 await page.getByRole('button',{name:'Publish',exact:true}).first().click();
 const confirm=page.getByRole('button',{name:'Publish',exact:true});if(await confirm.count())await confirm.last().click();
 await page.waitForFunction(()=>window.wp.data.select('core/editor').getCurrentPostAttribute('status')==='publish');
 console.log('PASS: human can publish the reviewed page through the real WordPress editor');
 const call=async(name,args,id)=>{const r=await page.request.post(base+'/wp-json/wpgptvibe/v1/mcp',{headers:{Authorization:'Bearer '+token,Accept:'application/json, text/event-stream','MCP-Protocol-Version':'2025-06-18'},data:{jsonrpc:'2.0',id,method:'tools/call',params:{name,arguments:args}}});assert.equal(r.status(),200);return(await r.json()).result;};
 const current=await call('get_page_blocks',{id:fixture.draft_id},101);const hash=current.structuredContent.result.hash;
 const denied=await call('apply_page_changes',{id:fixture.draft_id,expected_hash:hash,operations:[{type:'duplicate',path:[0]}],confirm_live:true,operation_id:'browser-live-bypass-test'},102);
 assert.equal(denied.isError,true);assert.match(JSON.stringify(denied),/Manual review/);
 const original=await call('get_page_blocks',{id:fixture.source_id},103);assert.equal(original.structuredContent.result.hash,fixture.source_hash);
 console.log('PASS: authenticated HTTP MCP cannot edit a published review copy or change the original');
}catch(e){await page.screenshot({path:'/tmp/wpgptvibe-review-failure.png',fullPage:true});console.error('URL:',page.url());console.error((await page.locator('body').innerText()).slice(0,2500));throw e;}finally{await browser.close();}
