import { createHmac, randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';
import { config } from '../config.js';

export type AdminSession = {
  username: string;
  issuedAt: number;
  expiresAt: number;
  nonce: string;
};

const COOKIE_NAME = 'wpgptvibe_admin';
const SESSION_SECONDS = 12 * 60 * 60;

function b64url(input: Buffer | string): string {
  return Buffer.from(input).toString('base64url');
}

function sign(payload: string): string {
  return createHmac('sha256', config.sessionSecret).update(payload).digest('base64url');
}

function constantEqual(a: string, b: string): boolean {
  const aa = Buffer.from(a);
  const bb = Buffer.from(b);
  return aa.length === bb.length && timingSafeEqual(aa, bb);
}

export function hashAdminPassword(password: string, salt = randomBytes(16).toString('hex')): string {
  const hash = scryptSync(password, salt, 32).toString('hex');
  return `scrypt$${salt}$${hash}`;
}

export function verifyAdminPassword(password: string): boolean {
  const configured = config.adminPasswordHash;
  if (!configured) return false;
  const [version, salt, expected] = configured.split('$');
  if (version !== 'scrypt' || !salt || !expected) return false;
  const actual = scryptSync(password, salt, 32).toString('hex');
  return constantEqual(actual, expected);
}

export function createAdminSession(username: string): string {
  const now = Math.floor(Date.now() / 1000);
  const session: AdminSession = {
    username,
    issuedAt: now,
    expiresAt: now + SESSION_SECONDS,
    nonce: randomBytes(18).toString('base64url'),
  };
  const payload = b64url(JSON.stringify(session));
  return `${payload}.${sign(payload)}`;
}

export function readAdminSession(cookieHeader?: string): AdminSession | null {
  const cookies = Object.fromEntries((cookieHeader ?? '').split(';').map((part) => {
    const index = part.indexOf('=');
    return index < 0 ? ['', ''] : [part.slice(0, index).trim(), part.slice(index + 1).trim()];
  }).filter(([key]) => key));

  const token = cookies[COOKIE_NAME];
  if (!token) return null;
  const [payload, signature] = token.split('.');
  if (!payload || !signature || !constantEqual(sign(payload), signature)) return null;

  try {
    const session = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8')) as AdminSession;
    const now = Math.floor(Date.now() / 1000);
    if (session.expiresAt < now || session.username !== config.adminUsername) return null;
    return session;
  } catch {
    return null;
  }
}

export function adminCookie(token: string): string {
  return `${COOKIE_NAME}=${token}; Path=/; HttpOnly; SameSite=Strict; Max-Age=${SESSION_SECONDS}${config.cookieSecure ? '; Secure' : ''}`;
}

export function clearAdminCookie(): string {
  return `${COOKIE_NAME}=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0${config.cookieSecure ? '; Secure' : ''}`;
}

export function csrfToken(session: AdminSession): string {
  return createHmac('sha256', config.sessionSecret).update(`csrf:${session.nonce}`).digest('base64url');
}

export function verifyCsrf(session: AdminSession, token: string | undefined): boolean {
  return Boolean(token && constantEqual(csrfToken(session), token));
}
