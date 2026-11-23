// Creates an admin account (port of admin/database/create-admin.php).
//   node scripts/create-admin.mjs "Full Name" email@example.org [super_admin|administrator|editor]
// The password is read from the ADMIN_PASSWORD environment variable, or asked for.
import bcrypt from 'bcryptjs';
import mysql from 'mysql2/promise';
import readline from 'node:readline/promises';
import fs from 'node:fs';

for (const f of ['.env.local', '.env']) {
  if (!fs.existsSync(f)) continue;
  for (const line of fs.readFileSync(f, 'utf8').split('\n')) {
    const m = /^([A-Z_]+)=(.*)$/.exec(line.trim());
    if (m && process.env[m[1]] === undefined) process.env[m[1]] = m[2];
  }
}
const [name, emailRaw, role = 'super_admin'] = process.argv.slice(2);
const email = String(emailRaw ?? '').trim().toLowerCase();
if (!name || !email) {
  console.error('Usage: node scripts/create-admin.mjs "Full Name" email@example.org [role]');
  process.exit(1);
}
if (!['super_admin', 'administrator', 'editor'].includes(role)) {
  console.error(`Unknown role '${role}'. Valid roles: super_admin, administrator, editor`);
  process.exit(1);
}
let password = process.env.ADMIN_PASSWORD ?? '';
if (!password) {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  password = (await rl.question(`Password for ${email} (min 12 characters, shown as you type): `)).trim();
  rl.close();
}
if (password.length < 12) {
  console.error('Password must be at least 12 characters.');
  process.exit(1);
}
const db = await mysql.createConnection({
  host: process.env.DB_HOST,
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER,
  password: process.env.DB_PASS ?? '',
  database: process.env.DB_NAME,
});
const [rows] = await db.query('SELECT id FROM admin_users WHERE email = ? LIMIT 1', [email]);
if (rows.length) {
  console.error('An account with that email already exists.');
  process.exit(1);
}
await db.query('INSERT INTO admin_users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)', [
  name,
  email,
  await bcrypt.hash(password, 10),
  role,
  'active',
]);
console.log(`Created ${role} account for ${email}.`);
await db.end();
