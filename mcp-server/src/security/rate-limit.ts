type Bucket={count:number;resetAt:number};
const buckets=new Map<string,Bucket>();

export function checkRateLimit(key:string,limit:number,windowMs:number):{ok:boolean;remaining:number;retryAfterSeconds:number}{
  const now=Date.now();
  const current=buckets.get(key);
  if(!current||current.resetAt<=now){
    buckets.set(key,{count:1,resetAt:now+windowMs});
    return {ok:true,remaining:Math.max(0,limit-1),retryAfterSeconds:Math.ceil(windowMs/1000)};
  }
  current.count+=1;
  if(current.count>limit){
    return {ok:false,remaining:0,retryAfterSeconds:Math.max(1,Math.ceil((current.resetAt-now)/1000))};
  }
  return {ok:true,remaining:Math.max(0,limit-current.count),retryAfterSeconds:Math.max(1,Math.ceil((current.resetAt-now)/1000))};
}

export function requestIdentity(headers:Record<string,string|string[]|undefined>,remoteAddress?:string):string{
  const forwarded=headers['x-forwarded-for'];
  const first=Array.isArray(forwarded)?forwarded[0]:forwarded?.split(',')[0]?.trim();
  return first||remoteAddress||'unknown';
}
