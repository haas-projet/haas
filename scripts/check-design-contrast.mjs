#!/usr/bin/env node
/** Vérifie les paires sRGB opaques de référence, sans dépendance ni accès réseau. */
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname, isAbsolute } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
function luminance(hex) {
  if (typeof hex !== 'string' || !/^#[0-9a-fA-F]{6}$/.test(hex)) {
    throw new Error(`Couleur invalide (hex opaque à six chiffres requis): ${String(hex)}`);
  }
  const values = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255);
  const [r,g,b] = values.map(c => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
function contrast(a,b) {
  const la = luminance(a), lb = luminance(b);
  return (Math.max(la,lb) + 0.05) / (Math.min(la,lb) + 0.05);
}
function locate(arg) { return isAbsolute(arg) ? arg : resolve(root, arg); }
try {
  const args = process.argv.slice(2);
  let pairPath = resolve(root, 'docs/design/contrast-pairs.json');
  let outPath = null;
  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--self-test') {
      assert.equal(contrast('#000000','#FFFFFF'),21);
      assert.equal(contrast('#123456','#123456'),1);
      assert.throws(() => luminance('#fff'));
      assert.throws(() => luminance('transparent'));
      console.log('Self-tests: 4 contrôles réussis.');
      if (args.length === 1) process.exit(0);
    } else if (args[i] === '--pairs' || args[i] === '--write') {
      const option = args[i], value = args[++i];
      if (!value) throw new Error(`Valeur manquante pour ${option}`);
      if (option === '--pairs') pairPath = locate(value); else outPath = locate(value);
    } else throw new Error(`Argument inconnu: ${args[i]}`);
  }
  const {colors} = JSON.parse(readFileSync(resolve(root, 'docs/design/tokens.json'), 'utf8'));
  const {pairs} = JSON.parse(readFileSync(pairPath, 'utf8'));
  if (!Array.isArray(pairs) || pairs.length === 0) throw new Error('Aucune paire à contrôler.');
  let failures = 0;
  const rows = pairs.map(p => {
    if (!p.label || typeof p.minimum !== 'number' || p.minimum < 1 || p.minimum > 21) {
      throw new Error('Paire invalide: libellé et seuil numérique requis.');
    }
    const fg = colors[p.foreground] ?? p.foreground;
    const bg = colors[p.background] ?? p.background;
    const ratio = contrast(fg,bg);
    // Comparaison brute ; l'arrondi ne sert qu'à présenter le résultat.
    const pass = ratio >= p.minimum;
    if (!pass) failures++;
    return `| ${String(p.label).replaceAll('|','/')} | ${fg} | ${bg} | ${ratio.toFixed(4)}:1 | ${p.minimum}:1 | ${pass ? 'PASS' : 'FAIL'} |`;
  });
  const report = [
    '# HAAS — Contrastes des tokens de référence', '',
    `Exécution : ${new Date().toISOString()}. Paires : ${pairs.length}. Réussies : ${pairs.length-failures}. Échecs : ${failures}.`, '',
    'Calcul sRGB opaque / luminance relative. Ce contrôle porte sur les paires définies, pas sur une application rendue ni sur une conformité WCAG complète. Opacité, dégradé et couleurs héritées doivent être vérifiés dans le navigateur.', '',
    '| Usage | Premier plan | Arrière-plan | Rapport | Minimum | Résultat |',
    '|---|---|---|---:|---:|---|', ...rows, '',
    'Seuils documentaires : texte courant 4,5:1 ; limites fonctionnelles non textuelles 3:1. Les cibles et l’état de focus exigent aussi une revue contextuelle.',
    'Sources : https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html ; https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html', ''
  ].join('\n');
  if (outPath) writeFileSync(outPath, report, 'utf8');
  console.log(report);
  process.exitCode = failures ? 1 : 0;
} catch (error) {
  console.error(`Échec du contrôle : ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 2;
}
