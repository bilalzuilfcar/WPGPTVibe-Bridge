import { chromium } from '../../mcp-server/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
const base='https://127.0.0.1:8090';
try {
 await page.goto(base+'/wp-login.php');
 // Authenticate through WordPress's real login endpoint; editor interaction is the acceptance target.
 const login=await page.request.post(base+'/wp-login.php',{form:{log:'admin',pwd:'test-only-password','wp-submit':'Log In',redirect_to:base+'/wp-admin/',testcookie:'1'}});
 assert.match(login.url(),/wp-admin/, 'WordPress login must establish an authenticated test session');
 await page.goto(base+'/wp-admin/');
 const id=fs.readFileSync('/tmp/wpgptvibe-test-page','utf8').trim();
 await page.goto(base+'/wp-admin/post.php?post='+id+'&action=edit');
 await page.waitForFunction(()=>window.wp?.data?.select('core/block-editor')?.getBlocks()?.length>0);
 await page.getByRole('button',{name:'Close',exact:true}).first().click({timeout:3000}).catch(()=>{});
 const frame=page.locator('iframe[name="editor-canvas"]');
 const canvas=await frame.count()?page.frameLocator('iframe[name="editor-canvas"]'):page;
 const headline=canvas.locator('[contenteditable="true"]').filter({hasText:'Human editable headline'}).first();
 await headline.fill('Edited visually without code');
 await page.waitForFunction(()=>JSON.stringify(window.wp.data.select('core/block-editor').getBlocks()).includes('Edited visually without code'));
 await page.evaluate(async()=>{await window.wp.data.dispatch('core/editor').savePost();});
 await page.reload();
 await page.waitForFunction(()=>JSON.stringify(window.wp?.data?.select('core/block-editor')?.getBlocks()||[]).includes('Edited visually without code'));
 const invalid=await page.evaluate(()=>{
  const walk=bs=>bs.some(b=>b.isValid===false||walk(b.innerBlocks||[]));return walk(window.wp.data.select('core/block-editor').getBlocks());
 });assert.equal(invalid,false,'Saved page has no invalid blocks');
 console.log('PASS: page text edited visually, saved and reopened without invalid blocks');
 const initialCount=await page.evaluate(()=>window.wp.data.select('core/block-editor').getBlocks().length);
 await page.evaluate(()=>{const b=window.wp.data.select('core/block-editor').getBlocks()[0];window.wp.data.dispatch('core/block-editor').duplicateBlocks([b.clientId]);});
 await page.evaluate(async()=>{await window.wp.data.dispatch('core/editor').savePost();});
 await page.reload();
 await page.waitForFunction(expected=>window.wp?.data?.select('core/block-editor')?.getBlocks()?.length===expected,initialCount+1);
 console.log('PASS: WordPress editor section duplication survives save and reopen');
 await page.goto(base+'/wp-admin/tools.php?page=wpgptvibe');
 await page.getByRole('heading',{name:'Connect ChatGPT',exact:true}).waitFor();
 assert.equal(await page.locator('textarea[name="redirects"]').count(),1);
 console.log('PASS: connection settings load in the real WordPress dashboard');
 assert.equal(errors.length,0,errors.join('\n'));
 await page.setViewportSize({width:390,height:844});
 await page.goto(base+'/?page_id='+id+'&preview=true');
 await page.getByText('Edited visually without code',{exact:true}).first().waitFor();
 const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2);
 assert.equal(overflow,false,'Mobile page should not overflow');
 console.log('PASS: generated page renders on mobile without horizontal overflow');
} catch(error) {
 await page.screenshot({path:'/tmp/wpgptvibe-browser-failure.png',fullPage:true});
 console.error('Page errors:',errors);
 console.error('Current test URL:',page.url());
 console.error('Visible test page:',(await page.locator('body').innerText()).slice(0,2000));
 throw error;
} finally {await browser.close();}
