// Disposable test server only. WordPress remains configured for HTTPS.
const https = require('node:https');
const http = require('node:http');
const fs = require('node:fs');
https.createServer({key:fs.readFileSync('/tmp/wpgptvibe-test.key'),cert:fs.readFileSync('/tmp/wpgptvibe-test.crt')},(req,res)=>{
 const upstream=http.request({hostname:'127.0.0.1',port:8091,path:req.url,method:req.method,headers:req.headers},r=>{res.writeHead(r.statusCode,r.headers);r.pipe(res);});
 upstream.on('error',()=>{res.writeHead(502);res.end('Test backend unavailable');});req.pipe(upstream);
}).listen(8090,'127.0.0.1');
