import { hashAdminPassword } from '../web/auth.js';

const password = process.env.WPGPTVIBE_ADMIN_PASSWORD?.trim();
if (!password || password.length < 12) {
  throw new Error('Set WPGPTVIBE_ADMIN_PASSWORD to at least 12 characters before running admin:hash-password.');
}

console.log(hashAdminPassword(password));
