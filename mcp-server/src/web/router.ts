import type { IncomingMessage, ServerResponse } from 'node:http';
import { config } from '../config.js';
import { databaseEnabled, ensureSchema } from '../storage/database.js';
import { checkRateLimit, requestIdentity } from '../security/rate-limit.js';
import { listActivity, recordActivity } from '../storage/activity.js';
import { deleteSite, getSite, listSites, upsertSite } from '../storage/sites.js';
import { callBridge } from '../wordpress/client.js';
import { adminCookie, clearAdminCookie, createAdminSession, csrfToken, readAdminSession, verifyAdminPassword, verifyCsrf } from './auth.js';
import { activityPage, dashboardPage, loginPage, siteDetailPage, siteFormPage, sitesPage } from './ui.js';

const MAX_BODY=64*1024;

function send(res:ServerResponse,status:number,payload:string,type='text/html; charset=utf-8',headers:Record<string,string>={}):void{res.writeHead(status,{'content-type':type,'cache-control':'no-store','x-content-type-options':'nosniff','x-frame-options':'DENY','referrer-policy':'no-referrer',...headers});res.end(payload);}
function redirect(res:ServerResponse,location:string,headers:Record<string,string>={}):void{res.writeHead(303,{location,'cache-control':'no-store',...headers});res.end();}

async function formBody(req:IncomingMessage):Promise<URLSearchParams>{
 let size=0; const chunks:Buffer[]=[];
 for await(const chunk of req){const b=Buffer.isBuffer(chunk)?chunk:Buffer.from(chunk);size+=b.length;if(size>MAX_BODY)throw new Error('Request body too large.');chunks.push(b);}
 return new URLSearchParams(Buffer.concat(chunks).toString('utf8'));
}

function withMessage(path:string,key:'message'|'error',value:string):string{const u=new URL(path,'http://wpgptvibe.local');u.searchParams.set(key,value);return u.pathname+u.search;}

export async function handleWeb(req:IncomingMessage,res:ServerResponse,url:URL):Promise<boolean>{
 if(url.pathname==='/health'){
   try{await ensureSchema();send(res,200,JSON.stringify({ok:true,service:'wpgptvibe',version:'0.3.0',database:databaseEnabled()?'mysql':'file',browser_testing:config.browserTesting}),'application/json');}
   catch(error){send(res,503,JSON.stringify({ok:false,service:'wpgptvibe',version:'0.3.0',database:databaseEnabled()?'mysql':'file',error:error instanceof Error?error.message:'database error'}),'application/json');}
   return true;
 }

 if(!url.pathname.startsWith('/admin'))return false;

 if(url.pathname==='/admin/login'){
   if(req.method==='GET'){send(res,200,loginPage(url.searchParams.get('error')||undefined));return true;}
   if(req.method==='POST'){
     const identity=requestIdentity(req.headers,req.socket.remoteAddress);
     const rate=checkRateLimit('admin-login:'+identity,config.adminLoginAttemptsPer15Minutes,15*60_000);
     if(!rate.ok){send(res,429,'Too many login attempts. Try again later.','text/plain',{'retry-after':String(rate.retryAfterSeconds)});return true;}
     const form=await formBody(req); const username=form.get('username')||''; const password=form.get('password')||'';
     if(!config.adminPasswordHash||username!==config.adminUsername||!verifyAdminPassword(password)){redirect(res,withMessage('/admin/login','error','Invalid username or password.'));return true;}
     redirect(res,'/admin',{'set-cookie':adminCookie(createAdminSession(username))});return true;
   }
 }

 if(url.pathname==='/admin/logout'){redirect(res,'/admin/login',{'set-cookie':clearAdminCookie()});return true;}

 const session=readAdminSession(req.headers.cookie);
 if(!session){redirect(res,'/admin/login');return true;}
 const csrf=csrfToken(session);

 if(url.pathname==='/admin'&&req.method==='GET'){
   const [sites,activity]=await Promise.all([listSites(),listActivity(50)]);
   send(res,200,dashboardPage({sites,activity,databaseMode:databaseEnabled(),browserTesting:config.browserTesting}));return true;
 }
 if(url.pathname==='/admin/activity'&&req.method==='GET'){send(res,200,activityPage(await listActivity(300)));return true;}
 if(url.pathname==='/admin/sites'&&req.method==='GET'){send(res,200,sitesPage(await listSites()));return true;}
 if(url.pathname==='/admin/sites/new'&&req.method==='GET'){send(res,200,siteFormPage(csrf,undefined,url.searchParams.get('message')||undefined,url.searchParams.get('error')||undefined));return true;}

 if(url.pathname==='/admin/sites'&&req.method==='POST'){
   const form=await formBody(req);
   if(!verifyCsrf(session,form.get('csrf')||undefined)){send(res,403,'Invalid CSRF token.','text/plain');return true;}
   const siteId=String(form.get('site_id')||'').trim();
   try{
     await upsertSite({siteId,displayName:String(form.get('display_name')||'').trim(),siteUrl:String(form.get('site_url')||'').trim(),apiToken:String(form.get('api_token')||'').trim()});
     await recordActivity({operation:'site_register',target:String(form.get('site_url')||''),siteId,status:'success'});
     redirect(res,'/admin/sites/'+encodeURIComponent(siteId));
   }catch(error){redirect(res,withMessage('/admin/sites/new','error',error instanceof Error?error.message:'Unable to register site.'));}
   return true;
 }

 const match=url.pathname.match(/^\/admin\/sites\/([0-9a-f-]{36})(?:\/(test|delete))?$/i);
 if(match){
   const siteId=match[1]!; const action=match[2];
   if(req.method==='GET'&&!action){
     try{
       const full=await getSite(siteId);const {encryptedApiToken:_secret,apiToken:_token,...safe}=full;
       let info:any=undefined;let releases:any[]=[];let bridgeAudit:any[]=[];let connectionError:string|undefined;
       try{const results=await Promise.all([callBridge<any>(siteId,'GET','site'),callBridge<any[]>(siteId,'GET','theme/releases'),callBridge<any[]>(siteId,'GET','audit',{limit:25})]);info=results[0];releases=Array.isArray(results[1])?results[1]:[];bridgeAudit=Array.isArray(results[2])?results[2]:[];}
       catch(error){connectionError=error instanceof Error?error.message:'Unable to load live site data.';}
       send(res,200,siteDetailPage({csrf,site:safe,info,releases,bridgeAudit,message:url.searchParams.get('message')||undefined,error:url.searchParams.get('error')||connectionError}));
     }
     catch(error){redirect(res,withMessage('/admin/sites','error',error instanceof Error?error.message:'Site not found.'));}
     return true;
   }
   if(req.method==='POST'){
     const form=await formBody(req);
     if(!verifyCsrf(session,form.get('csrf')||undefined)){send(res,403,'Invalid CSRF token.','text/plain');return true;}
     if(action==='delete'){await deleteSite(siteId);await recordActivity({operation:'site_remove',siteId,target:siteId,status:'success'});redirect(res,'/admin/sites');return true;}
     if(action==='test'){
       const started=Date.now();
       try{const info=await callBridge<any>(siteId,'GET','site');await recordActivity({operation:'connection_test',siteId,target:'site',status:'success',durationMs:Date.now()-started});redirect(res,withMessage('/admin/sites/'+siteId,'message',`Connected successfully. Bridge ${info.plugin_version||'unknown'}.`));}
       catch(error){await recordActivity({operation:'connection_test',siteId,target:'site',status:'error',durationMs:Date.now()-started,errorMessage:error instanceof Error?error.message:'Connection failed'});redirect(res,withMessage('/admin/sites/'+siteId,'error',error instanceof Error?error.message:'Connection failed.'));}
       return true;
     }
     try{
       const existing=await getSite(siteId);const newToken=String(form.get('api_token')||'').trim();
       await upsertSite({siteId,displayName:String(form.get('display_name')||'').trim(),siteUrl:String(form.get('site_url')||'').trim(),apiToken:newToken||existing.apiToken,permissions:existing.permissions,pluginVersion:existing.pluginVersion});
       await recordActivity({operation:'site_update',siteId,target:String(form.get('site_url')||''),status:'success'});
       redirect(res,withMessage('/admin/sites/'+siteId,'message','Site settings saved.'));
     }catch(error){redirect(res,withMessage('/admin/sites/'+siteId,'error',error instanceof Error?error.message:'Unable to save site.'));}
     return true;
   }
 }

 send(res,404,'Not found','text/plain');return true;
}