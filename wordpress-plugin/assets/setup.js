(function () {
 'use strict';
 const button=document.getElementById('wpgptvibe-test-connection');
 const status=document.getElementById('wpgptvibe-connection-result');
 if(!button||!status)return;
 button.addEventListener('click',async function () {
  button.disabled=true;status.textContent='Checking your website connection…';
  try {
   const metadata=await fetch(WPGPTVibeSetup.metadata,{credentials:'same-origin',cache:'no-store'});
   if(!metadata.ok)throw new Error('WordPress REST access is blocked. Check your security or cache settings.');
   const resource=await metadata.json();
   if(resource.resource!==WPGPTVibeSetup.mcp)throw new Error('The website returned a different MCP URL. Check the WordPress site address.');
   const authorization=await fetch(WPGPTVibeSetup.authorization,{credentials:'same-origin',cache:'no-store'});
   if(!authorization.ok)throw new Error('OAuth discovery is blocked. Check permalinks and your hosting rules.');
   const oauth=await authorization.json();
   if(!oauth.token_endpoint||!oauth.authorization_endpoint)throw new Error('Authorization settings are incomplete.');
   const endpoint=await fetch(WPGPTVibeSetup.mcp,{credentials:'same-origin',cache:'no-store'});
   if(endpoint.status!==401||!endpoint.headers.get('www-authenticate'))throw new Error('The MCP endpoint did not return the expected secure login response.');
   status.textContent='Your website is ready to connect. Add the MCP URL in ChatGPT and approve the WordPress permissions.';
  }catch(error){status.textContent=error instanceof SyntaxError?'The website returned a page instead of connection data. Check hosting, permalinks and security rules.':error.message;}
  finally{button.disabled=false;}
 });
})();
