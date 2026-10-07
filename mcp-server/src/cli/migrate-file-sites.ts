import { config } from '../config.js';
import { importLegacySitesFromFile } from '../storage/sites.js';

if(!config.databaseUrl){
  throw new Error('DATABASE_URL must be configured before migrating the legacy file registry.');
}

const result=await importLegacySitesFromFile();
console.log(JSON.stringify(result,null,2));
