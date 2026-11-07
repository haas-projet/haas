#!/usr/bin/env node
/** Vérifie les octets et la couverture des seuls fichiers suivis par Git. */
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const tracked = new Set(execFileSync('git', ['ls-files', '--cached', '-z'], {
  cwd: root, encoding: 'utf8',
}).split('\0').filter(file => file && file !== 'SHA256SUMS'));
const rows = readFileSync(resolve(root, 'SHA256SUMS'), 'utf8').trimEnd().split(/\r?\n/);
const seen = new Set();
const failures = [];
for (const row of rows) {
  const match = /^([0-9a-f]{64})  (.+)$/.exec(row);
  if (!match) {
    failures.push('Ligne de manifeste invalide');
    continue;
  }
  const [, expected, file] = match;
  if (!tracked.has(file) || seen.has(file)) {
    failures.push(`Fichier non suivi ou dupliqué : ${file}`);
    continue;
  }
  seen.add(file);
  try {
    const actual = createHash('sha256').update(readFileSync(resolve(root, file))).digest('hex');
    if (actual !== expected) failures.push(`Empreinte périmée : ${file}`);
  } catch {
    failures.push(`Fichier illisible : ${file}`);
  }
}
for (const file of tracked) {
  if (!seen.has(file)) failures.push(`Empreinte absente : ${file}`);
}
if (failures.length) {
  console.error(failures.join('\n'));
  console.error(`${failures.length} erreur(s) dans SHA256SUMS`);
  process.exitCode = 1;
} else {
  console.log(`${seen.size} empreintes vérifiées ; tous les fichiers suivis sont couverts.`);
}
