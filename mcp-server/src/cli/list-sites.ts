import { listSites } from '../storage/sites.js';

console.log(JSON.stringify(await listSites(), null, 2));
