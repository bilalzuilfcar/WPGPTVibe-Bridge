import { chromium } from '../../mcp-server/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const fixture=JSON.parse(fs.readFileSync('/tmp/wpgptvibe-spectra-test.json','utf8'));
const base='https://127.0.0.1:8090';
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
async function loaded(){await page.waitForFunction(()=>window.wp?.data?.select('core/block-editor')?.getBlocks()?.length>0);}
async function save(){await page.evaluate(async()=>{await window.wp.data.dispatch('core/editor').savePost();if(window.wp.data.select('core/editor').isEditedPostDirty())throw new Error('Editor did not save');});}
async function valid(){assert.equal(await page.evaluate(()=>{const walk=bs=>bs.every(b=>b.isValid!==false&&b.name!=='core/missing'&&walk(b.innerBlocks||[]));return walk(window.wp.data.select('core/block-editor').getBlocks());}),true,'All actual builder blocks must remain valid');}
try{
 await page.goto(base+'/wp-login.php');
 await page.request.post(base+'/wp-login.php',{form:{log:'admin',pwd:'test-only-password','wp-submit':'Log In',redirect_to:base+'/wp-admin/',testcookie:'1'}});
 await page.goto(base+'/wp-admin/post.php?post='+fixture.id+'&action=edit');await loaded();
 await page.getByRole('button',{name:'Close',exact:true}).first().click({timeout:2000}).catch(()=>{});
 await valid();
 const registered=await page.evaluate(()=>['spectra/container','spectra/content','spectra/buttons','spectra/button'].every(n=>!!window.wp.blocks.getBlockType(n)));
 assert.equal(registered,true,'Actual Spectra editor registrations must be loaded');
 const frame=page.locator('iframe[name="editor-canvas"]');
 const canvas=await frame.count()?page.frameLocator('iframe[name="editor-canvas"]'):page;
 const headline=canvas.locator('[contenteditable="true"]').filter({hasText:'MCP native Spectra headline'}).first();
 await headline.fill('Visually edited Spectra headline');
 await page.waitForFunction(()=>JSON.stringify(window.wp.data.select('core/block-editor').getBlocks()).includes('Visually edited Spectra headline'));
 await save();await page.reload();await loaded();await valid();
 assert.equal(await page.evaluate(()=>JSON.stringify(window.wp.data.select('core/block-editor').getBlocks()).includes('Visually edited Spectra headline')),true);
 console.log('PASS: native Spectra heading edited visually and reopened without invalid blocks');
 // Select a real core Image, then replace it through WordPress's media dialog.
 await page.evaluate(()=>{const find=bs=>{for(const b of bs){if(b.name==='core/image')return b;const nested=find(b.innerBlocks||[]);if(nested)return nested;}};window.wp.data.dispatch('core/block-editor').selectBlock(find(window.wp.data.select('core/block-editor').getBlocks()).clientId);});
 await page.getByRole('button',{name:'Replace',exact:true}).click();
 await page.getByRole('menuitem',{name:/Media Library|Open Media Library/i}).click();
 const dialog=page.getByRole('dialog');await dialog.waitFor();
 await dialog.getByRole('tab',{name:'Media Library',exact:true}).click().catch(()=>{});
 await dialog.locator('.attachment[data-id="'+fixture.image_ids[1]+'"]').click();
 await dialog.locator('.media-button-select').click();
 await page.waitForFunction(id=>{const walk=bs=>bs.some(b=>(b.name==='core/image'&&b.attributes.id===id)||walk(b.innerBlocks||[]));return walk(window.wp.data.select('core/block-editor').getBlocks());},fixture.image_ids[1]);
 await save();await page.reload();await loaded();await valid();
 console.log('PASS: native image replaced through the visual media library and saved');
 const count=await page.evaluate(()=>window.wp.data.select('core/block-editor').getBlocks().length);
 await page.evaluate(()=>{const store=window.wp.data.select('core/block-editor');const b=store.getBlocks()[0];window.wp.data.dispatch('core/block-editor').duplicateBlocks([b.clientId]);});
 await page.waitForFunction(n=>window.wp.data.select('core/block-editor').getBlocks().length===n,count+1);
 await page.evaluate(()=>{const b=window.wp.data.select('core/block-editor').getBlocks()[1];window.wp.data.dispatch('core/block-editor').moveBlocksDown([b.clientId]);});
 await save();await page.reload();await loaded();await valid();
 assert.equal(await page.evaluate(()=>window.wp.data.select('core/block-editor').getBlocks().length),count+1);
 console.log('PASS: Spectra section duplication and reordering survive editor save/reopen');
 await page.setViewportSize({width:390,height:844});await page.goto(base+'/?page_id='+fixture.id+'&preview=true');
 await page.getByText('Visually edited Spectra headline',{exact:true}).first().waitFor();
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2),false,'Mobile page must not overflow');
 const cards=await page.locator('.wp-block-spectra-container').filter({has:page.getByText('Professional certification',{exact:true})}).count();assert.ok(cards>0,'Native cards render');
 assert.equal(errors.length,0,errors.join('\n'));
 console.log('PASS: native Spectra page renders under Astra '+fixture.theme+' on mobile without overflow (Spectra '+fixture.spectra+')');
}catch(error){await page.screenshot({path:'/tmp/wpgptvibe-spectra-failure.png',fullPage:true});console.error('URL:',page.url());console.error('Page errors:',errors);console.error((await page.locator('body').innerText()).slice(0,2000));throw error;}finally{await browser.close();}
