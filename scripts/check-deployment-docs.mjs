#!/usr/bin/env node
/** Audit documentaire local. Aucune vérification d'infrastructure réelle. */
import {readFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import assert from 'node:assert/strict';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const read=p=>readFileSync(resolve(root,p),'utf8'); const checks=[];
function check(name,fn){try{fn();checks.push({name,status:'PASS'});}catch(e){checks.push({name,status:'FAIL',error:e.message});}}
const tasks=JSON.parse(read('docs/execution/tasks.json'));
check('Cadrage et décision de déploiement actifs',()=>{assert.equal(tasks.document_status,'community_and_help_offers_design');assert.equal(tasks.latest_decision,'ADR-006');assert.equal(tasks.deployment_decision,'ADR-004');assert.ok(tasks.source_alignment.includes('Mail CADEV'));});
check('Un VPS et un run global dans le modèle',()=>{const x=JSON.parse(read('ops/templates/inventory.example.json'));assert.equal(x.backend.instance_count,1);assert.equal(x.lab.max_running_global,1);assert.equal(x.frontend.provider,'Vercel');});
check('Douze sous-lots, quatorze contrôles',()=>{assert.equal([...read('docs/deployment/DEPLOYMENT_SUBTASKS.md').matchAll(/^## DEP\d{2} /gm)].length,12);assert.equal([...read('docs/quality/DEPLOYMENT_GATE.md').matchAll(/^\| DEP-AC\d{2} \|/gm)].length,14);});
check('Origine API absolue et cookies bornés',()=>{assert.match(read('ops/templates/frontend.vercel.env.example'),/VITE_API_URL=https:\/\/api\.haas\.example\.com/);assert.match(read('ops/templates/backend.systalink.env.example'),/SESSION_DOMAIN=\.haas\.example\.com/);});
check('Plans et licences non présumés',()=>{const x=read('docs/deployment/BUDGET.md');assert.ok(x.includes('55 625,60'));assert.ok(x.includes('20 USD'));assert.ok(x.includes('Hobby'));assert.ok(read('docs/product/CAHIER_DES_CHARGES.md').includes('Ultimate Plus'));});
check('Sources et approbations documentées',()=>{assert.ok(read('docs/sources/REFERENCES_DEPLOIEMENT.md').includes('[D13]'));assert.ok(read('docs/quality/DEPLOYMENT_GATE.md').includes('GO_PRODUCTION'));});
check('Pas de directives anciennes dans tâches actuelles',()=>{for(const t of tasks.tasks){assert.ok(!t.work.includes('cookies host-only'));assert.ok(!t.work.includes('sous même origine'));}});
const failed=checks.filter(c=>c.status==='FAIL').length;
console.log(JSON.stringify({scope:'Documentation uniquement, aucun serveur testé',checks,passed:checks.length-failed,failed},null,2));process.exit(failed?1:0);
