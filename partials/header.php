<?php
require_once __DIR__ . '/../includes/vault_check.php';

// ✅ TOUJOURS charger config.php (au cas où)
if (!defined('APP_LANG')) {
require_once __DIR__ . '/../config.php';
}

// ✅ TOUJOURS exécuter le tracker (sortir de la condition)
require_once __DIR__.'/../includes/security_tracker.php';

// ✅ Mailing compagne tracker
// session_start(); deja lancer dans config.php
// TRACKING D’ENTRÉE MARKETING (conditionnel)
if (isset($_GET['src']) && $_GET['src'] !== '') {
require_once __DIR__ . '/../public/tracker_click.php';
}
if (isset($_GET['src'])) {
$_SESSION['mailing_source'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['src']);
}

// ✅ COOKIE AFIN DE TOUJOURS VERIFIER L'AGE LEGALE 360 JOURS
require_once __DIR__ . '/../age-gate_handler.php';

// ✅ B2B / B2C gate
require_once __DIR__ . '/../b2b_b2c_handler.php';

// ✅ BALISE IN BODY INFORMATIONS FOR PRODUCT.PHP
$bodyDataAttrs = [
'lang-bottles' => t('bouteilles'),
'lang-welcome' => t('Offre de bienvenue'),
'lang-free' => t('Quantité libre'),
'lang-placeholder' => t('Nb bouteilles'),
'lang-hint' => t('Sélectionnez une quantité'),
'lang-unit' => t('unité'),
'lang-unit-price' => t('€ / unité'),
'lang-unit-hint' => t('Prix unitaire HT'),
'lang-bottle' => t('€ / bouteille')
];
$bodyAttrStr = '';
foreach ($bodyDataAttrs as $k => $v) {
$bodyAttrStr .= ' data-' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
}

?>
<!DOCTYPE html>
<html lang="<?= APP_LANG ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Catalogue Maison Champagne Gardet 1895 B2B') ?></title>

<?php if (isset($canonicalUrl)): ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<?php endif; ?>

<?php if (isset($hreflangUrls)): ?>
<?php foreach ($hreflangUrls as $lng => $url): ?>
<link rel="alternate" hreflang="<?= $lng ?>" href="<?= htmlspecialchars($url) ?>">
<?php endforeach; ?>
<?php endif; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Italiana&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<!-- SEO multilingue -->
<link rel="alternate" hreflang="fr" href="<?= BASE_URL ?>fr/">
<link rel="alternate" hreflang="de" href="<?= BASE_URL ?>de/">
<link rel="alternate" hreflang="en" href="<?= BASE_URL ?>en/">
<link rel="alternate" hreflang="x-default" href="<?= BASE_URL ?>">

<!-- Favicon CHAMPAGNE GARDET B2B -->
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/favicon/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>assets/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>assets/favicon/favicon-16x16.png">

<!-- Apple / Mobile -->
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>assets/favicon/apple-touch-icon.png">

<!-- Android / PWA -->
<link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_URL ?>assets/favicon/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?= BASE_URL ?>assets/favicon/android-chrome-512x512.png">

<meta name="theme-color" content="#000000">

<!-- Adaptation mobile -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="google-site-verification" content="WgCR4ae5Egw_wbDBPupzyn2o9fJC-s7R7egBm8fi4zM">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GGE35VRG63"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());

gtag('config', 'G-GGE35VRG63');
</script>
<style>
/* --------------------- CSS AGE GATE -------------------*/
#age-gate {
position: fixed;
inset: 0;
background: radial-gradient(circle at top, #1a1a1a, #000);
z-index: 9999999;
display: flex;
align-items: center;
justify-content: center;
font-family: 'Italiana' !important;
}

.age-box {
max-width: 420px;
text-align: center;
padding: 40px;
border-radius: 14px;
background: rgba(20,20,20,0.95);
box-shadow: 0 30px 80px rgba(0,0,0,0.6);
color: #f5f5f5;
}

.age-box h1 {
font-size: 22px;
margin-bottom: 20px;
letter-spacing: 0.5px;
position: relative;
font-family: 'Italiana' !important;
}

.age-box h1::after {
display: block;
width: 80px;
height: 2px;
margin: 12px auto 0;
background: linear-gradient(
to right,
transparent,
#d4af37,
transparent
);
}

.age-box p {
font-size: 15px;
opacity: 0.85;
margin-bottom: 30px;
}

.age-actions {
display: flex;
gap: 12px;
}

.age-actions button,
.age-actions a {
flex: 1;
padding: 12px 0;
border-radius: 8px;
border: none;
font-size: 14px;
text-decoration: none;
cursor: pointer;
}

.age-yes {
background: linear-gradient(135deg, #c9a24d, #8c6a2a);
color: #000;
font-weight: 600;
}

.age-no {
background: transparent;
border: 1px solid #444;
color: #ccc;
}

/* ===============================
POPUP B2B / B2C – CHOIX VISITEUR
=============================== */

#b2b-b2c-gate {
position: fixed;
inset: 0;
background: radial-gradient(circle at top, #1a1a1a, #000);
z-index: 9999998; /* juste sous age-gate */
display: flex;
align-items: center;
justify-content: center;
font-family: 'Roboto', "Helvetica Neue", Arial, sans-serif;
}

/* Boîte centrale */
.b2b-b2c-box {
max-width: 860px;
width: 92%;
padding: 40px 10px;
border-radius: 16px;
background: rgb(0 0 0 / 96%);
box-shadow: 0 30px 80px rgba(0,0,0,0.65);
color: #f2f2f2;
}

/* En-tête */
.b2b-b2c-header {
text-align: center;
margin-bottom: 36px;
}

.b2b-b2c-header h1 {
font-size: 26px;
letter-spacing: 0.6px;
margin-bottom: 6px;
}

.b2b-b2c-header span {
font-size: 14px;
opacity: 0.75;
letter-spacing: 1px;
}

/* Ligne or */
.b2b-b2c-header::after {
content: "";
display: block;
width: 120px;
height: 2px;
margin: 16px auto 0;
background: linear-gradient(
to right,
transparent,
#d4af37,
transparent
);
}

/* Colonnes */
.b2b-b2c-columns {
display: grid;
grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
gap: 10px;
}

/* Carte */
.b2b-b2c-card {
padding: 30px 10px;
border-radius: 14px;
background: rgb(14 14 14 / 95%);
box-shadow: inset 0 0 0 1px rgba(212,175,55,0.15);
display: flex;
flex-direction: column;
justify-content: space-between;
}

/* Titres cartes */
.b2b-b2c-card h2 {
font-size: 20px;
margin-top:0;
margin-bottom: 10px;
color: #d4af37;
letter-spacing: 0.5px;
text-align: center;
}

.b2b-b2c-card h3 {
font-size: 14px;
text-transform: uppercase;
opacity: 0.7;
margin-bottom: 16px;
}

/* Texte */
.b2b-b2c-card p {
font-size: 14.5px;
line-height: 1.55;
opacity: 0.9;
margin-bottom: 26px;
}

/* Code promo */
.promo-code {
display: inline-flex;
align-items: center;
gap: 8px;
padding: 10px 14px;
border-radius: 8px;
background: rgba(212,175,55,0.12);
border: 1px dashed rgba(212,175,55,0.6);
color: #d4af37;
font-weight: 600;
letter-spacing: 1px;
margin-bottom: 22px;
cursor: pointer;
}

/* Boutons */
.b2b-b2c-card a,
.b2b-b2c-card button {
display: block;
text-align: center;
padding: 14px 0;
border-radius: 10px;
font-size: 14px;
font-weight: 600;
text-decoration: none;
border: none;
cursor: pointer;
transition: transform 0.15s ease,
box-shadow 0.15s ease,
opacity 0.15s ease;
}

/* Bouton B2B */
.btn-b2b {
background: linear-gradient(135deg, #d4af37, #b9972f);
color: #111;
box-shadow: 0 6px 18px rgba(212,175,55,0.35);
}

/* Bouton B2C */
.btn-b2c {
background: transparent;
border: 1px solid rgba(212,175,55,0.6);
color: #d4af37;
}

/* Hover */
.b2b-b2c-card a:hover,
.b2b-b2c-card button:hover {
transform: translateY(-1px);
box-shadow: 0 8px 22px rgba(0,0,0,0.45);
}

.b2b-b2c-hero {
max-width: 250px;
width: 100%;
height: auto;
display: block;
margin: 0 auto 15px;
border-radius: 15px;
}

/* --------------------- EFFET NEIGE ----------------------*/

/* Conteneur neige */
#snow-container {
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
pointer-events: none; /* Pas d'interaction avec la neige */
z-index: 9999;
overflow: hidden;
}
.snowflake {
position: absolute;
top: -10px;
width: 1px;
height: 1px;
background: white;
border-radius: 50%;
opacity: 0.8;
animation-name: fall;
animation-timing-function: linear;
animation-iteration-count: infinite;
}

@keyframes fall {
0% { transform: translateY(0) translateX(0); opacity: 0.8; }
100% { transform: translateY(100vh) translateX(var(--x-offset)); opacity: 0.1; }
}

/* --------------------- CSS MENU LEGAL LINKS -------------------*/

/* ===== LEGAL SLIDE – ANCRAGE VIEWPORT ===== */
#legal-slide {
position: fixed;
right: 0 !important; /* 🔒 verrou absolu */
margin: 0 !important;
padding: 0 !important;
height: 48px;
display: flex;
align-items: center;
z-index: 999999;
pointer-events: auto;
font-family: 'Roboto', Arial, serif;
top: 0;
}

/* --- Carré flèche STRICTEMENT collé --- */
#legal-slide .legal-arrow {
width: 48px;
height: 48px;
background: linear-gradient(145deg, #dcae70, #d4af37);
display: flex;
align-items: center;
justify-content: center;
font-size: 22px;
cursor: pointer;
border-radius: 6px 0 0 6px;
box-shadow: -6px 6px 16px rgba(0,0,0,0.25);

/* 🔒 aucun décalage possible */
margin: 0;
padding: 0;
}

/* --- Menu qui s’ouvre vers la gauche --- */
#legal-slide .legal-links {
height: 48px;
display: flex;
align-items: center;
gap: 14px;
padding-left: 0;
padding-right: 0;
background:#000;
border-radius: 6px 0 0 6px;
box-shadow: -12px 8px 28px rgba(0,0,0,0.28);
max-width: 0;
overflow: hidden;
white-space: nowrap;
transition:
max-width 2s cubic-bezier(.25,.8,.25,1),
padding-left 0.6s ease,
padding-right 0.6s ease;
}

/* --- Hover desktop --- */
#legal-slide:hover .legal-links {
max-width: 1200px;
padding-left: 14px;
padding-right: 14px;
transition-delay: 0.2s;
}

/* --- Ouverture contrôlée JS (mobile) --- */
#legal-slide.open .legal-links {
max-width: 1200px;
padding-left: 14px;
padding-right: 14px;
transition-delay: 0s;
}

/* --- Liens --- */
#legal-slide .legal-links a {
color: #d3d3d3;
font-weight: 600;
font-size: 15px;
text-decoration: none;
}

#legal-slide .legal-links a:hover {
color: #d3d3d3;
text-decoration: underline;
}

.sep { color: #ccc; }

@media (hover: none) {
#legal-slide:hover .legal-links {
max-width: 0 !important;
padding-left: 0 !important;
padding-right: 0 !important;
}
}

/* --- Popup Modal --- */
.legal-popup {
display: none;
position: fixed;
z-index: 10000;
left: 0; top: 0; width: 100%; height: 100%;
overflow: auto;
background: rgba(0,0,0,0.7);
backdrop-filter: blur(3px);
padding-top: 50px;
}
.legal-popup.show { display: block; }

.legal-popup-content {
background: #fff9f2;
margin: 5% auto;
padding: 25px;
width: 80%;
max-width: 850px;
border-radius: 12px;
position: relative;
transform: translateY(-50px);
animation: slideDown 0.4s forwards;
box-shadow: 0 12px 32px rgba(0,0,0,0.4);
}
@keyframes slideDown { to { transform: translateY(0); } }

.legal-popup-text {
max-height: 70vh;
overflow-y: auto;
line-height: 1.6em;
font-family: 'Roboto', Arial, sans-serif;
color: #333;
text-align: justify;;
}

.legal-close {
position: absolute;
top: 1px;
right: 8px;
font-size: 30px;
font-weight: bold;
cursor: pointer;
color: #a36c28;
}
.legal-close:hover { color: #7a4812; }

/* --------------------- MENU DYNAMIQUE ------------------*/

.pdf-menu-cinema-absolute {
position: relative;
display: inline-block;
z-index: 1000;
margin-right: 10px;
}

/* Titre menu */
.pdf-menu-title {
padding: 12px 28px;
background: linear-gradient(145deg, #2b2b2b, #1a1a1a);
color: lightgrey;
font-weight: bold;
font-size: 16px;
letter-spacing: 0.5px;
border-radius: 6px;
cursor: pointer;
transition: all 0.4s ease, text-shadow 0.4s ease, box-shadow 0.4s ease;
box-shadow: 0 5px 15px rgba(0,0,0,0.25);
display: flex;
justify-content: space-between;
align-items: center;
}

.pdf-menu-title:hover {
color: lightsalmon;
text-shadow: 0 0 10px lightsalmon, 0 0 20px lightsalmon;
transform: translateY(-3px);
box-shadow: 0 8px 25px rgba(0,0,0,0.35), 0 0 15px rgba(255,160,122,0.3);
}

/* Flèche */
.pdf-menu-arrow {
margin-left: 10px;
font-size: 12px;
transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* Dropdown flottant */
.pdf-menu-dropdown {
position: absolute;
top: 100%;
left: 0;
min-width: 260px;
max-height: 320px;
overflow-y: auto;
background-color: #1c1c1c;
border-radius: 8px;
box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 30px rgba(255,160,122,0.15);
opacity: 0;
transform: translateY(20px);
transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
pointer-events: none;
}

/* Liens PDF */
.pdf-menu-dropdown a {
display: block;
padding: 12px 20px;
color: lightgrey;
text-decoration: none;
font-weight: 500;
transition: background 0.25s, color 0.25s, text-shadow 0.3s;
}

.pdf-menu-dropdown a:hover {
background-color: #2f2f2f;
color: lightsalmon;
text-shadow: 0 0 8px lightsalmon;
}

/* Hover desktop */
@media (hover: hover) {
.pdf-menu-cinema-absolute:hover .pdf-menu-dropdown {
opacity: 1;
transform: translateY(0);
pointer-events: auto;
}
.pdf-menu-cinema-absolute:hover .pdf-menu-arrow {
transform: rotate(180deg);
}
}

/* ===== SCROLLBAR PREMIUM NOIR ===== */

/* Chrome / Edge / Safari */
.pdf-menu-dropdown::-webkit-scrollbar {
width: 8px;
}

.pdf-menu-dropdown::-webkit-scrollbar-track {
background: #0f0f0f; /* fond noir */
border-radius: 10px;
}

.pdf-menu-dropdown::-webkit-scrollbar-thumb {
background: #333; /* curseur sombre */
border-radius: 10px;
border: 2px solid #0f0f0f;
}

.pdf-menu-dropdown::-webkit-scrollbar-thumb:hover {
background: lightsalmon; /* hover premium */
}

/* Firefox */
.pdf-menu-dropdown {
scrollbar-width: auto;
scrollbar-color: #333 #0f0f0f;
}

/* Hover UNIQUEMENT desktop réel */
@media (hover: hover) and (pointer: fine) {
.pdf-menu-cinema-absolute:hover .pdf-menu-dropdown {
opacity: 1;
transform: translateY(0);
pointer-events: auto;
}

.pdf-menu-cinema-absolute:hover .pdf-menu-arrow {
transform: rotate(180deg);
}
}

/* ÉTAT OUVERT — piloté par JS */
.pdf-menu-cinema-absolute.open .pdf-menu-dropdown {
opacity: 1;
transform: translateY(0);
pointer-events: auto;
}

.pdf-menu-cinema-absolute.open .pdf-menu-arrow {
transform: rotate(180deg);
}

/* --------------------- CSS DIAPORAMA -------------------*/

.collection-title {
height:15px;
text-align: center;
font-size: 1rem;
margin: 1rem 0 1rem;
padding: 1rem 0;
position: relative;
background: linear-gradient(to right, transparent, #eeb176 50%, transparent);
color: #000000; /* texte transparent pour l’effet gravé */
font-weight: bold;
text-transform: uppercase;
letter-spacing: 2px;

/* effet gravé 
text-shadow:
1px 1px 0 rgba(255, 255, 255, 0.6), /* lumière en haut à gauche */
-1px -1px 0 rgba(0, 0, 0, 0.4); /* ombre en bas à droite */
}

:root {
--gold: #d4af37;
--gold-soft: #e6c97a;
--black-deep: #0b0b0b;
}

#champagne-trigger {
position: fixed;
bottom: var(--spacing-large, 80px);
right: var(--spacing-large, 10px);
width: 44px;
height: 44px;
background: linear-gradient(145deg,#e6c97a,#d4af37);
border-radius: 50%;
cursor: pointer;
z-index: 1000;
display: flex;
align-items: center;
justify-content: center;
font-size: 20px;
line-height: 1;
color: #0b0b0b;
box-shadow:0 4px 12px rgba(212, 175, 55, 0.35),inset 0 1px 1px rgba(255,255,255,0.4);
transition:transform 0.25s ease,
box-shadow 0.25s ease;
}

#champagne-trigger:hover {
transform: scale(1.08);
box-shadow:
0 6px 18px rgba(212, 175, 55, 0.5),
inset 0 1px 1px rgba(255,255,255,0.6);
}

/* Overlay cinématique */
#champagne-overlay {
position: fixed;
bottom: 0;
right: 0;
width: 0;
height: 0;
background:
radial-gradient(circle at 30% 30%, rgba(212,175,55,0.08), transparent 60%),
var(--black-deep);
overflow: hidden;
z-index: 1000000;
transition: width 1.1s cubic-bezier(.77,0,.18,1),
height 1.1s cubic-bezier(.77,0,.18,1);
}

/* Ouverture */
#champagne-overlay.open {
width: 100vw;
height: 100vh;
}

/* Diaporama */
.champagne-diapo {
width: 100%;
height: 100%;
position: relative;
}

/* Slides */
.champagne-diapo img {
position: absolute;
inset: 0;
width: 100%;
height: 100%;
object-fit: cover;
opacity: 0;
transform: scale(1.04);
transition:
opacity 1.6s ease,
transform 3s ease;
cursor: pointer;
}

/* Slide actif */
.champagne-diapo img.active {
opacity: 1;
transform: scale(1);
}

/* --------------------- EVALUATION 1 - 5 ETOILES CLIENTS -------------------*/
.evaluate-link {
display:inline-flex;
align-items:center;
gap:2;
color: #d4af37;
text-decoration: none;
white-space:nowrap;
line-height:1;
font-size: 13px;
font-weight: 500;
margin-top: 10px;
padding: 4px 10px;
border: 1px solid rgba(212,175,55,0.4);
border-radius: 20px;
background: rgba(212,175,55,0.08);
transition: all 0.2s ease;
}

.evaluate-link:hover {
background: rgba(212,175,55,0.18);
border-color: rgba(212,175,55,0.8);
}

.evaluate-link:active {
opacity: 0.7;
}

/* --------------------- EVALUATION 1 - 5 ETOILES CLIENTS POP UP -------------------*/
/* =========================
MODAL – ÉVALUATION COMMANDE
========================= */

.modal {
display:none;
position: fixed;
inset: 0;
background: rgba(0,0,0,0.75);
align-items: center;
justify-content: center;
z-index: 99999999;
}

.modal.show{
	display:flex;
}

.modal-content {
background: #141414;
border-radius: 14px;
padding: 25px;
width: 100%;
max-width: 420px;
color: #eee;
box-shadow: 0 20px 50px rgba(0,0,0,0.6);
position: relative;
}

/* ---------- Titre ---------- */
.modal-content h3 {
margin: 0 0 18px;
font-size: 20px;
font-weight: 600;
color: #d4af37;
text-align: center;
}

/* ---------- Labels ---------- */
.modal-content label {
display: block;
margin: 14px 0 6px;
font-size: 13px;
font-weight: 500;
color: #bbb;
}

/* ---------- Zone étoiles ---------- */
.modal-content .stars {
display: flex;
gap: 6px;
font-size: 22px;
cursor: pointer;
margin-bottom: 4px;
}

.modal-content .stars span {
color: #555;
transition: color 0.15s ease, transform 0.1s ease;
}

.modal-content .stars span.active {
color: #d4af37;
}

.modal-content .stars span:hover {
transform: scale(1.15);
}

/* ---------- Textarea ---------- */
.modal-content textarea {
width: 100%;
min-height: 90px;
font-family: 'Roboto', arial;
margin-top: 6px;
resize: vertical;
background: #1d1d1d;
padding-right: 0px;
border: 1px solid #333;
border-radius: 8px;
color: #eee;
font-size: 14px;
line-height: 1.5;
transition: border-color 0.2s ease, background 0.2s ease;
}

.modal-content textarea::placeholder {
color: #666;
}

.modal-content textarea:focus {
outline: none;
border-color: #d4af37;
background: #1f1f1f;
}

/* ---------- Bouton ---------- */
.modal-content button.btn {
width: 100%;
margin-top: 18px;
padding: 12px;
background: linear-gradient(135deg, #d4af37, #b9972f);
border: none;
border-radius: 8px;
color: #111;
font-size: 14px;
font-weight: 600;
cursor: pointer;
transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.modal-content button.btn:hover {
transform: translateY(-1px);
box-shadow: 0 6px 16px rgba(212,175,55,0.35);
}

.modal-content button.btn:disabled {
opacity: 0.6;
cursor: not-allowed;
}

/* ---------- Bouton fermer ---------- */
.modal-content button[onclick] {
position: absolute;
top: 10px;
right: 12px;
color: #888;
transition: color 0.2s ease;
}

.modal-content button[onclick]:hover {
color: #fff;
}

/* ---------- Avis clients si pas de commande ---------- */
.empty-reviews {
margin-top: 40px;
max-width: 700px;
}

.empty-reviews h3 {
color: #d4af37;
margin-bottom: 20px;
font-size: 18px;
}

.review-line {
border-left: 3px solid #d4af37;
padding-left: 15px;
margin-bottom: 20px;
}

.review-head {
display: flex;
justify-content: space-between;
align-items: center;
}

.review-company {
color: #fff;
font-size: 14px;
}

.review-comment {
margin-top: 8px;
font-size: 14px;
color: #ccc;
font-style: italic;
line-height: 1.5;
}
</style>
</head>
<body <?= $bodyAttrStr ?>>

<header>

<?php
// ✅ TOUJOURS VERIFIER L'AGE LEGALE 360 JOURS
require_once __DIR__ . '/../age-gate.php';
require_once __DIR__ . '/../b2b_b2c.php';

?>

<div class="header-left">
<?php
// Logo dynamique selon la langue
$logos = [
'fr' => 'uploads/logo_gardet_fr.jpg',
'de' => 'uploads/logo_gardet_de.jpg',
'en' => 'uploads/logo_gardet_en.jpg',
];

// Fallback si logo spécifique n'existe pas
$logo_path = $logos[APP_LANG] ?? 'uploads/logo_gardet.jpeg';

// Vérifier si le fichier existe, sinon utiliser le logo par défaut
if (!file_exists(__DIR__ . '/../' . $logo_path)) {
$logo_path = 'uploads/logo_gardet.jpeg';
}
?>
<a href="<?= url('index.php') ?>">
<img src="<?= BASE_URL . $logo_path ?>"
alt="<?= t('Logo Champagne Gardet B2B') ?>"
class="logo">
</a>
<strong class="site-title"></strong>
</div>
<!--
<nav>
<a href="<?= url('index.php') ?>"><?= t('Catalogue') ?></a>
<a href="<?= url('cart.php') ?>"><?= t('Panier') ?></a>

<?php if (isClientLoggedIn()): ?>
<a href="<?= url('my_orders.php') ?>"><?= t('Mes commandes') ?></a>
<a href="<?= url('logout.php') ?>"><?= t('Déconnexion') ?></a>
<?php else: ?>
<a href="<?= url('login.php') ?>"><?= t('Connexion') ?></a>
<?php endif; ?>
</nav>
-->
<!-- Sélecteur de langue -->
<div class="lang-switcher">
<a href="<?= $hreflangUrls['fr'] ?? lang_url('fr') ?>"
class="<?= APP_LANG === 'fr' ? 'active' : '' ?>"
title="Français">
🇫🇷
</a>
<a href="<?= $hreflangUrls['de'] ?? lang_url('de') ?>"
class="<?= APP_LANG === 'de' ? 'active' : '' ?>"
title="Deutsch">
🇩🇪
</a>
<a href="<?= $hreflangUrls['en'] ?? lang_url('en') ?>"
class="<?= APP_LANG === 'en' ? 'active' : '' ?>"
title="English">
🇬🇧
</a>
</div>
<a href="<?= url('local_guide_request.php') ?>" class="local-guide-link">
🌍 <strong>1M views</strong> –
<span class="google-logo">
<span class="g">G</span>
<span class="o1">o</span>
<span class="o2">o</span>
<span class="g2">g</span>
<span class="l">l</span>
<span class="e">e</span>
</span> Local Guide
</a>
<?php require_once __DIR__ . '/legal_links.php'; ?>
<?php require_once __DIR__ . '/menu_dynamique_pdf.php'; ?>
<?php require_once __DIR__ . '/../includes/effet_neige.php'; ?>
</header>

<div class="container">
<?php displayFlash(); ?>