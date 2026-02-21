<?php
require_once __DIR__ . '/config.php';

// Déterminer la langue
$lang = $_GET['lang'] ?? 'de'; // DE par défaut

// Identifier le produit
$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;

if ($slug) {
$stmt = $pdo->prepare("
SELECT
p.id AS product_id,
t.slug
FROM products p
JOIN product_translations t ON p.id = t.product_id
WHERE t.slug = ? AND t.lang = ?
LIMIT 1
");
$stmt->execute([$slug, $lang]);
} elseif ($id) {
$stmt = $pdo->prepare("
SELECT p.*, t.*
FROM products p
JOIN product_translations t ON p.id = t.product_id
WHERE p.id = ? AND t.lang = ?
LIMIT 1
");
$stmt->execute([$id, $lang]);
} else {
header("Location: index.php");
exit();
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);
$productId = (int)$product['product_id'];

if (!$product) {
// Produit non trouvé → 404
header("HTTP/1.0 404 Not Found");
include __DIR__ . '/partials/header.php';
echo "<h1>" . t('Produit introuvable') . "</h1>";
include __DIR__ . '/partials/footer.php';
exit();
}

// Redirect si l’URL n’est pas canonique
$correctUrl = ($lang === 'de' ? BASE_URL : BASE_URL . $lang . '/') . $product['slug'];
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_contains($currentPath, $product['slug'])) {
header("HTTP/1.1 301 Moved Permanently");
header("Location: $correctUrl");
exit();
}

// Pour header.php : canonical + hreflang
$canonicalUrl = $correctUrl;
$hreflangUrls = [];
$languages = ['de','fr','en'];
foreach ($languages as $lng) {
$stmt = $pdo->prepare("
SELECT slug FROM product_translations
WHERE product_id = ? AND lang = ?
");
$stmt->execute([$productId, $lng]);
$translation = $stmt->fetch(PDO::FETCH_ASSOC);
if ($translation) {
$hreflangUrls[$lng] = ($lng === 'de' ? BASE_URL : BASE_URL . $lng . '/') . $translation['slug'];
}
}

// ============================================================================
// LOGIQUE CORRIGÉE : OFFRE PREMIÈRE COMMANDE
// ============================================================================
$clientId = $_SESSION['client_id'] ?? null;
$hasOrders = false;
// Si un client est connecté, vérifier s'il a déjà commandé
if ($clientId) {
// Vérifier que le client existe encore dans la base
$stmt = $pdo->prepare("SELECT 1 FROM clients WHERE id = ? LIMIT 1");
$stmt->execute([$clientId]);
$clientExists = (bool)$stmt->fetchColumn();
if (!$clientExists) {
// Client supprimé de la base → détruire la session
session_unset();
session_destroy();
session_start();
$clientId = null;
} else {
// Client valide → vérifier ses commandes
$stmt = $pdo->prepare("
SELECT 1
FROM orders
WHERE client_id = ?
LIMIT 1
");
$stmt->execute([$clientId]);
$hasOrders = (bool)$stmt->fetchColumn();
}
}
include __DIR__ . '/partials/header.php';

$id = (int)$product['product_id']; // ← $product est déjà résolu par slug ou id au-dessus
$lang = in_array(APP_LANG, ['fr', 'de', 'en']) ? APP_LANG : 'de';

// =========================
// TRACKING (PRO)
// =========================
require_once __DIR__ . '/public/tracker_business.php';

// 1. Toujours tracker la VUE du produit (s'exécute au chargement de la page)
trackBusinessEvent([
'type' => 'view_product',
'product_id' => $id
]);

// 2. Tracker l'AJOUT au panier (ne s'exécute QUE si le formulaire est posté)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty']) && is_array($_POST['qty'])) {
foreach ($_POST['qty'] as $key => $qty) {
$qty = (int)$qty;
if ($qty <= 0) continue;

if (str_starts_with($key, 'product_')) {
$productId = (int)substr($key, 8);
$formatId = null;
} else {
$formatId = (int)$key;
// retrouver product_id depuis format
$stmt = $pdo->prepare("SELECT product_id FROM product_formats WHERE id = ?");
$stmt->execute([$formatId]);
$productId = (int)$stmt->fetchColumn();
}

trackBusinessEvent([
'type' => 'add_to_cart',
'product_id' => $productId,
'format_id' => $formatId,
'qty' => $qty
]);
}
}

/* =========================
PRODUIT (MULTILINGUE + FALLBACK)
========================= */
$stmt = $pdo->prepare("
SELECT
p.*,
-- Nom avec fallback progressif
COALESCE(
NULLIF(pt.name, ''),
NULLIF(pt_fr.name, ''),
p.name
) AS name,
-- Sous-titre avec fallback progressif
COALESCE(
NULLIF(pt.subtitle, ''),
NULLIF(pt_fr.subtitle, ''),
p.subtitle
) AS subtitle,
-- Descriptions
COALESCE(
NULLIF(pt.short_description, ''),
NULLIF(pt_fr.short_description, ''),
p.short_description
) AS short_description,
COALESCE(
NULLIF(pt.long_description, ''),
NULLIF(pt_fr.long_description, ''),
p.long_description
) AS long_description,
COALESCE(
NULLIF(pt.custom_html, ''),
NULLIF(pt_fr.custom_html, ''),
p.custom_html
) AS custom_html
FROM products p
LEFT JOIN product_translations pt
ON pt.product_id = p.id
AND pt.lang = :lang
LEFT JOIN product_translations pt_fr
ON pt_fr.product_id = p.id
AND pt_fr.lang = 'fr'
WHERE p.id = :product_id
LIMIT 1
");
$stmt->execute([
'lang' => $lang,
'product_id'=> $id
]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
die(t('Produit introuvable'));
}
/* =========================
IMAGES PRODUIT (MULTIPLE)
========================= */
$stmt = $pdo->prepare("
SELECT image
FROM product_images
WHERE product_id = ? AND is_main = 1
ORDER BY position ASC
");
$stmt->execute([$id]);
$productImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Si aucune image dans product_images, utiliser l'image par défaut du produit
if (empty($productImages) && $product['image']) {
$productImages = [$product['image']];
}
/* =========================
MEDAILLE SUR IMAGE
========================= */
$stmt = $pdo->prepare("
SELECT image, title
FROM product_badges
WHERE product_id = ?
AND is_active = 1
AND is_on_image = 1
AND image IS NOT NULL
AND image != ''
ORDER BY position ASC
");
$stmt->execute([$id]);
$imageBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
/* =========================
FORMATS MULTILINGUES + FALLBACK
========================= */
$stmt = $pdo->prepare("
SELECT
id,
bottles_per_carton,
COALESCE(
NULLIF(
CASE :lang
WHEN 'fr' THEN format_fr
WHEN 'de' THEN format_de
WHEN 'en' THEN format_en
END,
''
),
format
) AS format
FROM product_formats
WHERE product_id = :product_id
ORDER BY id ASC
");
$stmt->execute([
'lang' => $lang,
'product_id' => $id
]);
$formats = $stmt->fetchAll(PDO::FETCH_ASSOC);
/* =========================
OFFRES PREMIÈRE COMMANDE (CORRIGÉ)
========================= */
$firstOrderOffers = [];
// CORRECTION: Afficher l'offre si:
// 1. Le client n'est PAS connecté (visiteur anonyme)
// 2. OU le client est connecté MAIS n'a jamais commandé
if (!$clientId || ($clientId && !$hasOrders)) {
$stmt = $pdo->prepare("
SELECT format_id, min_bottles
FROM b2b_first_order_offers
WHERE product_id = ?
AND is_active = 1
");
$stmt->execute([$id]);
foreach ($stmt->fetchAll() as $row) {
$firstOrderOffers[$row['format_id']] = (int)$row['min_bottles'];
}
}
/* =========================
PALIERS DE PRIX
========================= */
$stmt = $pdo->prepare("
SELECT format_id, min_bottles
FROM product_price_tiers
WHERE product_id = ? AND is_active = 1
ORDER BY min_bottles ASC
");
$stmt->execute([$id]);
$priceTiers = [];
foreach ($stmt->fetchAll() as $row) {
$priceTiers[$row['format_id']][] = (int)$row['min_bottles'];
}
/* =========================
BADGES
========================= */
$stmt = $pdo->prepare("
SELECT image
FROM product_badges
WHERE product_id = ?
AND is_active = 1
AND image IS NOT NULL
AND image != ''
ORDER BY position ASC
");
$stmt->execute([$id]);
$badges = $stmt->fetchAll();
/* =========================
ACCESSOIRES - MISE EN PAGE
========================= */
$hasFormats = !empty($formats);
$isAccessory = ($product['collection'] === 'ACCESSOIRES');
$isSimpleAccessory = $isAccessory && !$hasFormats;
?>

<div class="product-app">
<div class="product-hero">
<div class="product-image-wrap">
<?php if (!empty($productImages)): ?>
<img
id="product-main-image"
src="uploads/<?= htmlspecialchars($productImages[0]) ?>"
alt="<?= htmlspecialchars($product['name']) ?>"
style="cursor: pointer;"
onclick="nextImage()"
title="<?= t('Cliquez pour voir l\'image suivante') ?>"
>
<?php if (count($productImages) > 1): ?>
<div style="text-align:center; margin-top:8px; font-size:12px; color:#aaa;">
<span id="image-counter">1</span> / <?= count($productImages) ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($imageBadges)): ?>
<div class="image-badges">
<?php foreach ($imageBadges as $i => $b): ?>
<div class="image-badge badge-<?= $i ?>">
<?php
$path = 'uploads/' . $b['image'];
$ext = strtolower(pathinfo($b['image'], PATHINFO_EXTENSION));
if ($ext === 'svg' && file_exists($path)) {
clearstatcache(true, $path);
echo file_get_contents($path);
} else {
echo '<img src="' . htmlspecialchars($path) . '" alt="">';
}
?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<h1><?= htmlspecialchars($product['name']) ?></h1>
<?php if ($product['short_description']): ?>
<p class="short-desc">
<?= nl2br(htmlspecialchars($product['short_description'])) ?>
</p>
<?php endif; ?>
</div>
<?php if ($hasFormats): ?>
<section class="selection-block">
<h2></h2>
<div class="cards format-cards">
<?php foreach ($formats as $f): ?>
<div class="card format-card"
data-format="<?= $f['id'] ?>"
data-bpc="<?= (int)$f['bottles_per_carton'] ?>">
<strong><?= htmlspecialchars($f['format']) ?></strong>
<?php if (!$isAccessory): ?>
<span><?= $f['bottles_per_carton'] ?> <?= t('bt / carton') ?></span>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php if (!$isAccessory && $hasFormats): ?>
<section class="selection-block" id="units-calculator-block" style="display:none;">
<div class="units-wrapper">
<div class="units-header">
<span><?= t('Compteur d\'unités') ?></span>
<div class="units-dot bg-champagne animate-pulse"></div>
</div>
<div class="units-value" id="unitsValue">—</div>
<div class="units-label"><?= t('bouteilles') ?></div>
<div class="units-body">
<div class="units-bar">
<div class="units-fill" id="unitsFill"></div>
</div>
<div class="units-presets" id="unitsPresets"></div>
</div>
<div class="units-controls">
<button type="button" onclick="changeUnits(-1)">−</button>
<button type="button" onclick="changeUnits(1)">+</button>
</div>
</div>
</section>
<?php endif; ?>
<section class="selection-block">
<h2></h2>
<div class="cards quantity-cards"></div>
</section>
<section class="selection-block">
<section class="price-box">
<p class="price-label"><?= t('Prix HT') ?></p>
<p class="price-value">—</p>
<p class="price-hint"><?= $isSimpleAccessory ? t('Prix unitaire HT') : t('Sélectionnez un format et une quantité') ?></p>
</section>
</section>
<button class="btn-add" disabled><?= t('Ajouter au panier') ?></button>
<section class="selection-block">
<section class="accordion">
<button class="accordion-toggle"><?= t('Description & distinctions') ?></button>
<div class="accordion-content">
<?php if ($product['custom_html']): ?>
<div class="custom-html-content">
<?= $product['custom_html'] ?>
</div>
<?php endif; ?>
<?php /*if ($product['long_description']): ?>
<p><?= nl2br(htmlspecialchars($product['long_description'])) ?></p>
<?php endif; >
<?php if ($product['long_description']): ?>
<div class="long-description">
<?= nl2br(htmlspecialchars($product['long_description'])) ?>
</div>
<?php endif; ?>
<?php
$html = $product['custom_html'];
if (!empty($product['long_description'])) {
$longDesc = nl2br(htmlspecialchars($product['long_description']));
$html = str_replace(
'<div class="gardet-long-description"></div>',
'<div class="section gardet-long-description">' . $longDesc . '</div>',
$html
);
}
echo $html;
*/?>
<?php if (!empty($badges)): ?>
<div class="badges">
<?php foreach ($badges as $b):
if (empty($b['image'])) continue;
$path = 'uploads/' . $b['image'];
$ext = strtolower(pathinfo($b['image'], PATHINFO_EXTENSION));
?>
<div class="badge">
<?php if ($ext === 'svg' && file_exists($path)): ?>
<?= file_get_contents($path); ?>
<?php elseif (file_exists($path)): ?>
<img src="<?= htmlspecialchars($path) ?>" alt="">
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</section>
</section>
<div id="champagne-trigger-top">🍾</div>
<div id="champagne-overlay-top">
<div class="champagne-diapo">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_30.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_0.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_1.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_2.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_3.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_4.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_5.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_6.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_7.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_8.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_9.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_10.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_11.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_12.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_13.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_14.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_15.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_16.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_17.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_18.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_19.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_20.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_21.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_22.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_23.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_24.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_25.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_26.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_27.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_28.jpg" alt="Champagne Gardet B2B">
<img src="<?= BASE_URL ?>uploads/scenes/champagne_gardet_scene_29.jpg" alt="Champagne Gardet B2B">
</div>
</div>
</div>
<script>
const isAccessory = <?= $isAccessory ? 'true' : 'false' ?>;
const isSimpleAccessory = <?= $isSimpleAccessory ? 'true' : 'false' ?>;
</script>
<script>
const priceTiers = <?= json_encode($priceTiers) ?>;
const firstOrderOffers = <?= json_encode($firstOrderOffers) ?>;
</script>
<script>
let selectedFormat = null;
let bottlesPerCarton = 0;
let selectedBottles = null;
/* FORMAT */
document.querySelectorAll('.format-card').forEach(card => {
card.addEventListener('click', () => {
document.querySelectorAll('.format-card').forEach(c => c.classList.remove('active'));
card.classList.add('active');
selectedFormat = card.dataset.format;
bottlesPerCarton = parseInt(card.dataset.bpc);
buildQuantityCards();
initUnitsCalculator();
});
});
/* GÉNÉRATION AUTO DES QUANTITÉS */
function buildQuantityCards() {
const container = document.querySelector('.quantity-cards');
container.innerHTML = '';
selectedBottles = null;
// ✅ NE PLUS CRÉER LES CARTES QUANTITÉS ICI
// On ne garde QUE la carte manuelle
/* Quantité libre (TOUJOURS PRÉSENTE) */
container.appendChild(createManualQtyCard());
// ✅ Initialiser le calculateur d'unités (qui remplace les qty-cards)
initUnitsCalculator();
if (!isSimpleAccessory) {
resetPrice();
}
}
/* =========================
UNITS CALCULATOR
========================= */
function initUnitsCalculator() {
const block = document.getElementById('units-calculator-block');
if (!block) return;
block.style.display = 'block';
maxUnits = Math.max(
...(priceTiers[selectedFormat] || []),
bottlesPerCarton * 10
);
buildUnitsPresets();
setUnits(null);
}
function buildUnitsPresets() {
const container = document.getElementById('unitsPresets');
container.innerHTML = '';
let values = [];
if (firstOrderOffers[selectedFormat]) {
values.push(firstOrderOffers[selectedFormat]);
}
if (priceTiers[selectedFormat]) {
values = values.concat(priceTiers[selectedFormat]);
}
// 🔽 inversion visuelle haut → bas
values
.sort((a, b) => a - b) // logique métier
.reverse() // logique visuelle
.forEach(v => addPreset(v, v === firstOrderOffers[selectedFormat]));
}
function addPreset(value, highlight = false) {
const btn = document.createElement('button');
btn.textContent = value;
if (highlight) btn.innerHTML += ' ★';
btn.addEventListener('click', () => setUnits(value));
document.getElementById('unitsPresets').appendChild(btn);
}
function setUnits(val) {
selectedBottles = val;
document.getElementById('unitsValue').innerText = val ?? '—';
updateBar();
document.querySelectorAll('.units-presets button').forEach(b =>
b.classList.toggle('active', parseInt(b.textContent) === val)
);
if (val) updatePrice();
}
function changeUnits(delta) {
if (!selectedBottles) selectedBottles = bottlesPerCarton;
selectedBottles = Math.max(1, selectedBottles + delta);
setUnits(selectedBottles);
}
function updateBar() {
const percent = selectedBottles
? Math.min(100, (selectedBottles / maxUnits) * 100)
: 0;
document.getElementById('unitsFill').style.height = percent + '%';
}
/* CRÉATION CARD */
function createQtyCard(bottles, isFirst) {
const div = document.createElement('div');
div.className = 'card qty-card';
div.dataset.bottles = bottles;
// Traductions dynamiques via attributs data-*
const labelBottles = document.body.dataset.langBottles || 'bouteilles';
const labelWelcome = document.body.dataset.langWelcome || 'Offre de bienvenue';
div.innerHTML = `
<strong>${bottles} ${labelBottles}</strong>
${isFirst ? '<span>${labelWelcome}</span>' : ''}
`;
div.addEventListener('click', () => {
document.querySelectorAll('.qty-card').forEach(c => c.classList.remove('active'));
div.classList.add('active');
selectedBottles = bottles;
updatePrice();
});
return div;
}
function createManualQtyCard() {
const div = document.createElement('div');
div.className = 'card qty-card manual';
const labelFree = document.body.dataset.langFree || 'Quantité libre';
let placeholderNb;
if (isAccessory) {
placeholderNb = document.body.dataset.langUnit || 'unité';
} else {
placeholderNb = document.body.dataset.langPlaceholder || 'Nb bouteilles';
}
div.innerHTML = `
<strong>${labelFree}</strong>
<div class="manual-input-wrapper">
<button type="button" class="manual-btn manual-minus">−</button>
<input type="number" min="1" placeholder="${placeholderNb}">
<button type="button" class="manual-btn manual-plus">+</button>
</div>
`;
const input = div.querySelector('input');
const minusBtn = div.querySelector('.manual-minus');
const plusBtn = div.querySelector('.manual-plus');
div.addEventListener('click', (e) => {
if (!e.target.classList.contains('manual-btn')) {
document.querySelectorAll('.qty-card').forEach(c => c.classList.remove('active'));
div.classList.add('active');
input.focus();
}
});
// Bouton moins
minusBtn.addEventListener('click', (e) => {
e.stopPropagation();
const currentVal = parseInt(input.value) || 0;
if (currentVal > 1) {
input.value = currentVal - 1;
input.dispatchEvent(new Event('input'));
}
});
// Bouton plus
plusBtn.addEventListener('click', (e) => {
e.stopPropagation();
const currentVal = parseInt(input.value) || 0;
input.value = currentVal + 1;
input.dispatchEvent(new Event('input'));
});
input.addEventListener('input', () => {
selectedBottles = parseInt(input.value || 0);
if (selectedBottles > 0) {
updatePrice();
} else {
resetPrice();
}
});
return div;
}

/* RESET */
function resetPrice() {
document.querySelector('.price-value').innerText = '—';
const hintText = document.body.dataset.langHint || 'Sélectionnez une quantité';
document.querySelector('.price-hint').innerText = hintText;
document.querySelector('.btn-add').disabled = true;
document.querySelector('.btn-add').style.opacity = .5;
}
/* AJAX PRIX */
function updatePrice() {
// 🟡 ACCESSOIRE SANS FORMAT → prix direct
if (isSimpleAccessory) {
const unitPrice = <?= (float)$product['price'] ?>;
const qty = selectedBottles || 1;
const total = (unitPrice * qty).toFixed(2);
document.querySelector('.price-value').innerText = total + ' €';
document.querySelector('.price-hint').innerText =
unitPrice.toFixed(2) + ' ' +
(document.body.dataset.langUnitPrice || '€ / unité');
document.querySelector('.btn-add').disabled = false;
document.querySelector('.btn-add').style.opacity = 1;
return;
}
// 🟢 PRODUITS AVEC FORMATS (champagne OU accessoire)
fetch('<?= BASE_URL ?>includes/get_price.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: `format_id=${selectedFormat}&bottles=${selectedBottles}`
})
.then(r => r.json())
.then(data => {
if (!data.success) return;
const label = isAccessory
? (document.body.dataset.langUnitPrice || '€ / unité')
: (document.body.dataset.langBottle || '€ / bouteille');
document.querySelector('.price-value').innerText = data.total + ' €';
document.querySelector('.price-hint').innerText = data.unit_bottle + ' ' + label;
document.querySelector('.btn-add').disabled = false;
document.querySelector('.btn-add').style.opacity = 1;
});
}
/* AJOUT PANIER */
document.querySelector('.btn-add').addEventListener('click', () => {
const form = document.createElement('form');
form.method = 'POST';
form.action = '<?= url('cart.php') ?>';
const input = document.createElement('input');
input.type = 'hidden';
if (isSimpleAccessory) {
input.name = `qty[product_<?= $product['id'] ?>]`;
input.value = selectedBottles || 1;
} else {
const cartons = Math.ceil(selectedBottles / bottlesPerCarton);
input.name = `qty[${selectedFormat}]`;
input.value = cartons;
}
form.appendChild(input);
document.body.appendChild(form);
form.submit();
});
// ✅ AUTO-AFFICHAGE PRIX POUR ACCESSOIRE SIMPLE
if (isSimpleAccessory) {
buildQuantityCards();
selectedBottles = 1;
updatePrice();
}
</script>
<script>
document.querySelectorAll('.accordion-toggle').forEach(button => {
button.addEventListener('click', () => {
const content = button.nextElementSibling;
const isOpen = content.style.display === 'block';
content.style.display = isOpen ? 'none' : 'block';
});
});
</script>
<script>
// Carrousel d'images produit
const productImages = <?= json_encode($productImages) ?>;
let currentImageIndex = 0;
function nextImage() {
if (productImages.length <= 1) return;
currentImageIndex = (currentImageIndex + 1) % productImages.length;
const imgElement = document.getElementById('product-main-image');
const counterElement = document.getElementById('image-counter');
// Effet de transition
imgElement.style.opacity = '0.5';
setTimeout(() => {
imgElement.src = 'uploads/' + productImages[currentImageIndex];
imgElement.style.opacity = '1';
if (counterElement) {
counterElement.textContent = currentImageIndex + 1;
}
}, 150);
}
// Optionnel : navigation au clavier (flèches)
document.addEventListener('keydown', (e) => {
if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
nextImage();
}
});
</script>
<script>
const trigger = document.getElementById('champagne-trigger-top');
const overlay = document.getElementById('champagne-overlay-top');
const slides = document.querySelectorAll('.champagne-diapo img');
let index = 0;
let interval = null;

function startSlideshow() {
interval = setInterval(() => {
slides[index].classList.remove('active');
index = (index + 1) % slides.length;
slides[index].classList.add('active');
}, 3200);
}

function stopSlideshow() {
clearInterval(interval);
interval = null;
slides.forEach(s => s.classList.remove('active'));
index = 0;
}

trigger.addEventListener('click', () => {
if (overlay.classList.contains('open')) return;
overlay.classList.add('open');
slides[0].classList.add('active');
startSlideshow();
});

slides.forEach(slide => {
slide.addEventListener('click', () => {
overlay.classList.remove('open');
stopSlideshow();
});
});
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>