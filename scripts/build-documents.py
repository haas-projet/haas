#!/usr/bin/env python3
"""Generate the documentation PDF/HTML, not the HAAS application.
Requires: weasyprint, markdown-it-py, beautifulsoup4. Run from any directory.
"""
from pathlib import Path
import re, html
from markdown_it import MarkdownIt
from bs4 import BeautifulSoup
from weasyprint import HTML
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'livrables'; OUT.mkdir(exist_ok=True)
s=(ROOT/'docs/product/CAHIER_DES_CHARGES.md').read_text(encoding='utf-8')
md=MarkdownIt('commonmark',{'html':True,'breaks':False}).enable('table').enable('strikethrough')
chunks=list(re.finditer(r'^## (\d{2}) — (.+)$',s,re.M))
assert len(chunks)==61, len(chunks)
groups=[(1,6,'Cadrage'),(7,18,'Produit et collaboration'),(19,20,'Expérience utilisateur'),(21,28,'Architecture et sécurité'),(29,33,'Qualité et livraison'),(34,41,'Exécution et sources'),(42,50,'Atelier distinctif'),(51,52,'Déploiement'),(53,57,'Communauté et cadrage'),(58,61,'La rencontre par le coup de main')]
def group(i):return next(g for a,b,g in groups if a<=i<=b)
chapters=[];toc=[];lastgroup=None
for idx,m in enumerate(chunks):
 n=int(m.group(1));title=m.group(2);end=chunks[idx+1].start() if idx+1<len(chunks) else len(s)
 body=s[m.end():end].strip();body_html=md.render(body)
 soup=BeautifulSoup(body_html,'html.parser')
 for table in soup.find_all('table'):
  row=table.find('tr');headers=[x.get_text(' ',strip=True) for x in row.find_all(['th','td'])];count=len(headers)
  widths=None
  if headers[:2]==['Ordre','ID']:widths=[9,13,78]
  elif count==4 and headers[0]=='ID' and headers[1].startswith('Attendu'):widths=[10,56,14,20]
  elif count==4 and headers[0]=='ID' and headers[1]=='Contrôle':widths=[11,22,47,20]
  elif count==4 and headers[0]=='ID':widths=[10,11,14,65]
  elif count==2:widths=[31,69]
  elif count==3:widths=[23,35,42]
  elif count==4:widths=[17,22,37,24]
  elif count==5:widths=[22,19.5,19.5,19.5,19.5]
  if widths:
   colgroup=soup.new_tag('colgroup')
   for w in widths:
    col=soup.new_tag('col');col['style']=f'width:{w}%';colgroup.append(col)
   table.insert(0,colgroup)
  if n==49:table['class']=['task-table']
 for a in soup.find_all('a',href=True):
  if a['href'].startswith('http'):a['class']=['external-ref']
 chapters.append(f'<article class="chapter {"major" if n in [1,7,19,21,29,34,42,51,53,58] else ""}" id="sec-{n:02}"><div class="eyebrow">{n:02} / {html.escape(group(n).upper())}</div><h1>{html.escape(title)}</h1>{soup}</article>')
 if group(n)!=lastgroup:
  toc.append(f'<h3>{html.escape(group(n))}</h3>');lastgroup=group(n)
 toc.append(f'<p><a href="#sec-{n:02}"><b>{n:02}</b> {html.escape(title)}</a></p>')
css='''
@page { size:A4; margin:18mm 16mm 18mm; @top-left{content:"HAAS / CAHIER DES CHARGES";font-size:8pt;font-family:DejaVu Sans;color:#486064;} @top-right{content:"COMMUNAUTÉ · PROJETS · COLLABORATION";font-size:7pt;font-family:DejaVu Sans;color:#486064;} @bottom-left{content:"Équipe Delta  ·  1er octobre 2026";font-size:8pt;font-family:DejaVu Sans;color:#486064;} @bottom-right{content:counter(page)" / "counter(pages);font-size:8pt;font-family:DejaVu Sans;color:#486064;} }
@page cover{margin:0;@top-left{content:none;}@top-right{content:none;}@bottom-left{content:none;}@bottom-right{content:none;}}
*{box-sizing:border-box} body{font-family:DejaVu Sans,Arial,sans-serif;color:#102A2E;font-size:10pt;line-height:1.48;margin:0;}h1,h2,h3,h4{color:#102A2E;line-height:1.2;break-after:avoid;} h1{font-size:23pt;letter-spacing:-.5pt;margin:2mm 0 6mm;bookmark-level:1;}h2{font-size:15pt;margin:6mm 0 3mm;bookmark-level:2;}h3{font-size:12pt;margin:5mm 0 2.4mm;bookmark-level:none;}h4{font-size:10.6pt;bookmark-level:none;}p{margin:0 0 3.1mm;orphans:3;widows:3;}strong{font-weight:700;}a{color:#087F73;text-decoration:none;overflow-wrap:anywhere;}ul,ol{padding-left:5mm;margin:2mm 0 4mm;}li{margin:1mm 0;}code{font-family:DejaVu Sans Mono,monospace;font-size:8.1pt;color:#123B40;overflow-wrap:anywhere;}pre{white-space:pre-wrap;overflow-wrap:anywhere;font:8pt/1.35 DejaVu Sans Mono,monospace;border-left:3px solid #087F73;background:#F5F7F4;padding:3.5mm;break-inside:auto;}pre code{font-size:inherit;}blockquote{margin:4mm 0 5mm;border-left:3px solid #087F73;background:#ECF7F1;padding:3.7mm 4.5mm;font-weight:500;break-inside:avoid;}blockquote p:last-child{margin-bottom:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;margin:3.5mm 0 5mm;font-size:8.8pt;line-height:1.4;}thead{display:table-header-group;}th{background:#102A2E;color:white;font-weight:700;text-align:left;padding:2.6mm 2.6mm;vertical-align:top;overflow-wrap:anywhere;}td{border-bottom:1px solid #DCE5DF;padding:2.4mm 2.6mm;vertical-align:top;overflow-wrap:anywhere;}tbody tr:nth-child(even){background:#F5F7F4;}tr{break-inside:avoid;}td p{margin:0;}table.task-table{font-size:9pt;}table.task-table td{padding:1.9mm 2.8mm;}.chapter{break-before:auto;margin-top:9mm;}.chapter.major{break-before:page;margin-top:0;}.chapter .eyebrow{break-after:avoid;}.eyebrow{font-size:8.5pt;letter-spacing:1.2pt;color:#087F73;font-weight:700;margin-top:1mm;}.cover{page:cover;height:297mm;width:210mm;position:relative;padding:25mm 19mm;background:#F5F7F4;overflow:hidden;}.cover:after{content:"";position:absolute;right:-22mm;top:60mm;width:45mm;height:183mm;background:#BFE8D5;transform:rotate(10deg);z-index:-1;}.cover-logo{width:80mm;height:auto;margin-bottom:18mm;}.cover-kicker{color:#087F73;font-weight:700;font-size:9pt;letter-spacing:1.5pt;margin:0 0 10mm;}.cover h1{font-size:34pt;line-height:1.11;letter-spacing:-1.2pt;margin:0 0 7mm;bookmark-level:none;}.cover .lead{font-size:16pt;line-height:1.42;max-width:153mm;color:#23474B;margin-bottom:13mm;}.cover .subtitle{font-size:11pt;color:#486064;max-width:149mm;}.cover .rail{border-top:2px solid #087F73;border-bottom:1px solid #DCE5DF;margin-top:12mm;padding:6mm 0;display:flex;gap:6mm;}.cover .rail div{width:32%;font-size:10pt;}.cover .rail b{display:block;color:#087F73;font-size:8pt;letter-spacing:1pt;margin-bottom:2mm;}.cover .meta{position:absolute;left:19mm;bottom:17mm;font-size:9pt;line-height:1.65;color:#486064;}.toc{break-before:page;}.toc h1{margin-top:0;}.toc h3{font-size:9.2pt;letter-spacing:.7pt;text-transform:uppercase;color:#087F73;margin:4mm 0 1.4mm;}.toc p{font-size:9.2pt;line-height:1.35;margin:0 0 1.5mm;}.toc a{color:#102A2E;display:block;}.toc a b{display:inline-block;width:8mm;color:#486064;}.toc a:after{content:leader('.') target-counter(attr(href),page);}.note{font-size:9pt;color:#486064;}@media screen{body{background:#EEF2EE;}.cover,.toc,.chapter{max-width:210mm;padding:18mm 16mm;margin:8mm auto;background:white;box-shadow:0 6px 26px #102A2E12;}.cover{padding:25mm 19mm;}.toc a:after{content:none;}}
'''
cover='''<section class="cover"><img class="cover-logo" src="../assets/brand/haas-logo.png" alt="HAAS — Help as a Service"><p class="cover-kicker">ÉQUIPE DELTA / CADEV 2026</p><h1>Cahier<br>des charges</h1><p class="lead">Un projet.<br>Un coup de main.<br><strong>Une rencontre utile.</strong></p><p class="subtitle">Proposez une aide concrète, engagez un échange consenti et partagez ce qui fait avancer les projets. L’atelier documente certaines améliorations.</p><div class="rail"><div><b>COMMUNAUTÉ</b>Profils, projets<br>et connaissances</div><div><b>RÉALISATION</b>Laravel + React<br>Deux développeurs</div><div><b>LIVRAISON</b>Systalink + Vercel<br>Qodana + Actions</div></div><div class="meta">Cadrage CADEV et décision « Rencontre par le coup de main »<br>Document de conception · Implémentation et validation à réaliser<br>1er octobre 2026</div></section>'''
html_text='<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>HAAS — Cahier des charges</title><style>'+css+'</style></head><body>'+cover+'<section class="toc"><div class="eyebrow">UNE SEULE RÉFÉRENCE ACTIVE</div><h1>Sommaire</h1><p class="note">Le cadrage vient du mail fourni ; les modalités et exigences techniques sont les choix de conception HAAS. Les identifiants de suivi sont conservés.</p>'+''.join(toc)+'</section>'+''.join(chapters)+'</body></html>'
ht=OUT/'HAAS_Cahier_des_charges.html';ht.write_text(html_text,encoding='utf-8')
HTML(filename=str(ht)).write_pdf(str(OUT/'HAAS_Cahier_des_charges.pdf'), presentational_hints=True)
print('Generated',OUT/'HAAS_Cahier_des_charges.pdf')
