<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/shipping.php';
require_once __DIR__ . '/includes/unit_helper.php';
if (!isset($_SESSION['cart'])) {
$_SESSION['cart'] = [];
}
/* =========================
AJOUT DEPUIS FICHE PRODUIT (PATCH APPLIQUÉ)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty'])) {
// AJOUT ICI :
require_once __DIR__ . '/public/tracker_business.php';
foreach ($_POST['qty'] as $key => $qty) {
$qty = intval($qty);
if ($qty <= 0) continue;
if (str_starts_with($key, 'product_')) {
$pId = intval(substr($key, 8)); // On extrait l'ID numérique
if (!isset($_SESSION['cart'][$key])) { $_SESSION['cart'][$key] = 0; }
$_SESSION['cart'][$key] += $qty;
// TRACKER ICI :
trackBusinessEvent([
'type' => 'add_to_cart',
'product_id' => $pId,
'qty' => $qty,
'source' => 'product_page_simple'
]);
}
else {
$formatId = intval($key);
if (!isset($_SESSION['cart'][$formatId])) { $_SESSION['cart'][$formatId] = 0; }
$_SESSION['cart'][$formatId] += $qty;
// TRACKER ICI :
trackBusinessEvent([
'type' => 'add_to_cart',
'product_id' => $formatId,
'qty' => $qty,
'source' => 'product_page_format'
]);
}
}
header('Location: ' . url('cart.php'));
exit;
}
/* =========================
SUPPRESSION LIGNE
========================= */
if (isset($_GET['remove'])) {
// On ne force pas intval() ici car l'ID peut être "product_12"
$remove_id = $_GET['remove'];
unset($_SESSION['cart'][$remove_id]);
header('Location: ' . url('cart.php'));
exit;
}
include __DIR__ . '/partials/header.php';
?>
<h1><?= t('Votre panier') ?></h1>
<?php if (empty($_SESSION['cart'])) {
// Supprimer les cadeaux et choix associés
unset($_SESSION['cart_gifts'], $_SESSION['gift_choices']); ?>
<div class="cart-empty">
<p><?= t('Votre panier est vide.') ?></p>
<a href="<?= url('index.php') ?>" class="btn"><?= t('Retour au catalogue') ?></a>
</div>
<?php include __DIR__ . '/partials/footer.php'; exit;} ?>
<div class="cart-wrapper">
<?php
$total = 0;
// SÉPARATION DES CLES : FORMATS vs PRODUITS SIMPLES
$cart_keys = array_keys($_SESSION['cart']);
$format_ids = [];
$simple_product_ids = []; // Map: product_id => session_key
foreach ($cart_keys as $k) {
if (is_numeric($k)) {
$format_ids[] = intval($k);
} elseif (str_starts_with($k, 'product_')) {
$pId = intval(substr($k, 8));
if ($pId > 0) {
$simple_product_ids[$pId] = $k;
}
}
}
if (empty($format_ids) && empty($simple_product_ids)) {
// Cas théorique panier vide
echo '<div class="cart-empty"><p>' . t('Votre panier est vide.') . '</p></div>';
include __DIR__ . '/partials/footer.php'; exit;
}
$lang = in_array(APP_LANG, ['fr','de','en']) ? APP_LANG : 'fr';
$rows = [];
// 1. RÉCUPÉRATION DES FORMATS (Vins)
if (!empty($format_ids)) {
$placeholders = implode(',', array_fill(0, count($format_ids), '?'));
$stmt = $pdo->prepare("
SELECT
pf.id AS row_id,
'format' AS type,
p.unit_key,
pf.product_id,
pf.bottles_per_carton,
pf.volume_ml,
pf.price,
COALESCE(pt.name, p.name) AS name,
COALESCE(
NULLIF(
CASE ?
WHEN 'fr' THEN pf.format_fr
WHEN 'de' THEN pf.format_de
WHEN 'en' THEN pf.format_en
END,
''
),
pf.format
) AS format_name
FROM product_formats pf
JOIN products p ON p.id = pf.product_id
LEFT JOIN product_translations pt
ON pt.product_id = p.id
AND pt.lang = ?
WHERE pf.id IN ($placeholders)
");
$params = array_merge([$lang, $lang], $format_ids);
$stmt->execute($params);
$rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
// 2. RÉCUPÉRATION DES PRODUITS SIMPLES (Accessoires)
if (!empty($simple_product_ids)) {
$pIds = array_keys($simple_product_ids);
$placeholders = implode(',', array_fill(0, count($pIds), '?'));
$stmt = $pdo->prepare("
SELECT
p.id AS product_id,
'product' AS type,
p.unit_key,
1 AS bottles_per_carton,
0 AS volume_ml, -- Pas de volume pour accessoires
p.price,
COALESCE(pt.name, p.name) AS name,
'' AS format_name
FROM products p
LEFT JOIN product_translations pt
ON pt.product_id = p.id
AND pt.lang = ?
WHERE p.id IN ($placeholders)
");
$params = array_merge([$lang], $pIds);
$stmt->execute($params);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pRow) {
// On reconstruit une structure compatible
$sessionKey = 'product_' . $pRow['product_id'];
$pRow['row_id'] = $sessionKey; // L'ID utilisé dans le HTML sera la clé string
$rows[] = $pRow;
}
}
/* =========================
CALCUL POUR CADEAUX DANS PANIER
========================= */
$totalBottlesAll = 0;
foreach ($rows as $row) {
// Seuls les formats de type "bottle" comptent
if ($row['type'] === 'format' && $row['unit_key'] === 'bottle') {
$qtyCartons = $_SESSION['cart'][$row['row_id']];
$totalBottlesAll += $qtyCartons * $row['bottles_per_carton'];
}
}
$stmt = $pdo->query("SELECT * FROM b2b_gift_rules WHERE is_active = 1");
$giftRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
$giftSummary = [];
foreach ($giftRules as $rule) {
$pallet = (int)$rule['pallet_bottles'];
$qtyPerPallet = (int)$rule['qty_per_pallet'];
if ($rule['gift_type'] === 'flutes') {
$giftQty = intdiv($totalBottlesAll, $pallet);
} else {
$giftQty = intdiv($totalBottlesAll, $pallet) * $qtyPerPallet;
}
if ($giftQty <= 0) continue;
$giftSummary[$rule['gift_type']] = [
'gift_type' => $rule['gift_type'],
'qty' => $giftQty,
'requires_choice' => (bool)$rule['requires_choice'],
'allow_repeat' => (bool)$rule['allow_repeat'],
'allowed_products' => json_decode($rule['allowed_product_ids'], true),
'unit_price_ht' => (float)$rule['unit_price_ht'],
'is_gift' => 1
];
}
/* =========================
CALCUL GLOBAL DES BOUTEILLES (Pour dégressif)
========================= */
$globalBottleTotals = [];
foreach ($rows as $row) {
if ($row['type'] === 'format') {
$qty = $_SESSION['cart'][$row['row_id']];
$globalBottleTotals[$row['product_id']][$row['row_id']] =
($globalBottleTotals[$row['product_id']][$row['row_id']] ?? 0)
+ ($qty * $row['bottles_per_carton']);
}
}
/* =========================
BOUCLE D'AFFICHAGE
========================= */
foreach ($rows as $r):
$qty = $_SESSION['cart'][$r['row_id']];
$line = 0;

$unitKey = $r['unit_key'] ?? 'unit';
$unitLabelSingular = unitLabel($unitKey, 1, $lang);
$unitLabelPlural = unitLabel($unitKey, 2, $lang);

$infoLines = [];
$unitPriceLabel = '';
$totalUnits = 0;

if ($r['type'] === 'format') {

// =========================
// FORMAT VIN
// =========================
$totalUnits = $qty * $r['bottles_per_carton'];

$unitBottlePrice = resolveUnitPrice(
$pdo,
$r['product_id'],
$r['row_id'],
$totalUnits,
$_SESSION['client_id'] ?? 0
);

$pricePerCarton = $unitBottlePrice * $r['bottles_per_carton'];
$line = $pricePerCarton * $qty;

$infoLines[] = sprintf(
'%s – %d %s / %s',
htmlspecialchars($r['format_name']),
$r['bottles_per_carton'],
unitLabel($unitKey, $r['bottles_per_carton'], $lang),
t('carton')
);

$infoLines[] = sprintf(
'%s € / %s',
number_format($unitBottlePrice, 2, ',', ' '),
$unitLabelSingular
);

$infoLines[] = sprintf(
'%d %s %s',
$totalUnits,
unitLabel($unitKey, $totalUnits, $lang),
t('au total')
);

$unitPriceLabel = sprintf(
'%s € / %s',
number_format($pricePerCarton, 2, ',', ' '),
t('carton')
);

} else {

// =========================
// ACCESSOIRE / PRODUIT SIMPLE
// =========================
$totalUnits = $qty;
$unitPrice = (float)$r['price'];
$line = $unitPrice * $qty;

$infoLines[] = t('Vendu à l’unité');

$infoLines[] = sprintf(
'%s € / %s',
number_format($unitPrice, 2, ',', ' '),
$unitLabelSingular
);

$infoLines[] = sprintf(
'%d %s %s',
$totalUnits,
unitLabel($unitKey, $totalUnits, $lang),
t('au total')
);

$unitPriceLabel = sprintf(
'%s € / %s',
number_format($unitPrice, 2, ',', ' '),
$unitLabelSingular
);
}

$total += $line;
?>
<div class="cart-item" id="row-<?= $r['row_id'] ?>">
<div class="cart-info">
<h3><?= htmlspecialchars($r['name']) ?></h3>
<p class="format" id="info-<?= $r['row_id'] ?>">
<?= implode('<br>', $infoLines) ?>
</p>

<p class="price" id="price-<?= $r['row_id'] ?>">
<?= $unitPriceLabel ?>
</p>
</div>
<div class="cart-actions">
<div class="qty-stepper">
<button type="button" class="qty-btn plus" data-action="plus">+</button>
<input
type="text"
inputmode="numeric"
pattern="[0-9]*"
class="qty-input"
id="qty-<?= $r['row_id'] ?>"
name="qty[<?= $r['row_id'] ?>]"
data-id="<?= $r['row_id'] ?>"
value="<?= $qty ?>"
min="0"
autocomplete="off"
>
<button type="button" class="qty-btn minus" data-action="minus">−</button>
</div>
<span class="line-total" id="line-<?= $r['row_id'] ?>">
<?= number_format($line, 2, ',', ' ') ?> €
</span>
<a class="remove"
href="<?= url('cart.php') ?>?remove=<?= $r['row_id'] ?>"
onclick="return confirm('<?= t('Supprimer cette ligne ?') ?>')">✖</a>
</div>
</div>
<?php endforeach; ?>
<?php
/* =========================
GÉNÉRATION DES CADEAUX (OPTIMISÉ)
========================= */

// Calcul du giftSummary
$giftSummary = [];
$totalBottlesAll = 0;
foreach ($rows as $row) {
if ($row['type'] === 'format' && ($row['unit_key'] ?? '') === 'bottle') {
$qtyCartons = $_SESSION['cart'][$row['row_id']] ?? 0;
$totalBottlesAll += $qtyCartons * $row['bottles_per_carton'];
}
}

// Récup règles actives
$stmt = $pdo->query("SELECT * FROM b2b_gift_rules WHERE is_active = 1");
$giftRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparer liste unique de produits autorisés
$allAllowedProductIds = [];
foreach ($giftRules as $rule) {
$allowed = json_decode($rule['allowed_product_ids'], true) ?: [];
$allAllowedProductIds = array_merge($allAllowedProductIds, $allowed);
}
$allAllowedProductIds = array_unique($allAllowedProductIds);

// Précharger tous les produits autorisés en une seule requête
$productsById = [];
if (!empty($allAllowedProductIds)) {
$placeholders = implode(',', array_fill(0, count($allAllowedProductIds), '?'));
$stmt = $pdo->prepare("
SELECT p.id, COALESCE(pt.name, p.name) AS name
FROM products p
LEFT JOIN product_translations pt
ON pt.product_id = p.id AND pt.lang = ?
WHERE p.id IN ($placeholders)
ORDER BY name
");
$params = array_merge([$lang], $allAllowedProductIds);
$stmt->execute($params);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
$productsById[$p['id']] = $p['name'];
}
}

// Calcul giftSummary et quantité
foreach ($giftRules as $rule) {
$pallet = (int)$rule['pallet_bottles'];
$qtyPerPallet = (int)$rule['qty_per_pallet'];
if ($rule['gift_type'] === 'flutes') {
$giftQty = intdiv($totalBottlesAll, $pallet);
} else {
$giftQty = intdiv($totalBottlesAll, $pallet) * $qtyPerPallet;
}
if ($giftQty <= 0) continue;

$allowedProducts = json_decode($rule['allowed_product_ids'], true) ?: [];
$giftSummary[$rule['gift_type']] = [
'gift_type' => $rule['gift_type'],
'qty' => $giftQty,
'requires_choice' => (bool)$rule['requires_choice'],
'allowed_products' => $allowedProducts
];
}

// Stocker en session **avant rendu HTML**
$_SESSION['cart_gifts'] = $giftSummary;
?>

<div id="cart-gifts">
<?php foreach ($giftSummary as $gift): ?>
<div class="cart-item gift-item"
data-gift="<?= $gift['gift_type'] ?>"
data-required="<?= $gift['qty'] ?>">
<div class="cart-info" data-gift-label="<?= t('OFFERT') ?>">
<h3>🎁 <?= t(ucfirst($gift['gift_type'])) ?></h3>
<p class="format">
<?php if ($gift['gift_type'] === 'flutes'): ?>
<?= $gift['qty'] ?> × <?= t('Lot de 12 flûtes') ?>
<?php else: ?>
<?= $gift['qty'] ?> × <?= t('Sélection requise') ?>
<?php endif; ?>
<br><em><?= t('Choix obligatoire') ?></em>
</p>
</div>
<div class="cart-actions gift-actions">
<?php for ($i = 1; $i <= $gift['qty']; $i++): ?>
<select
class="gift-choice"
id="gift-<?= $gift['gift_type'] ?>-<?= $i ?>"
name="gifts[<?= $gift['gift_type'] ?>][]"
required
data-gift-type="<?= $gift['gift_type'] ?>"
>
<option value="">— <?= t('Choisir') ?> —</option>
<?php foreach ($gift['allowed_products'] as $prodId): ?>
<?php if (isset($productsById[$prodId])): ?>
<option value="<?= $prodId ?>"><?= htmlspecialchars($productsById[$prodId]) ?></option>
<?php endif; ?>
<?php endforeach; ?>
</select>
<?php endfor; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php
/* =========================
CALCUL DU VOLUME TOTAL ET CARTONS ÉQUIVALENTS
========================= */
$totalVolumeLiters = 0;
$realCartonsCount = 0;
$accessoriesCartons = 0; // Nouveau compteur pour les accessoires

foreach ($rows as $row) {
$qty = $_SESSION['cart'][$row['row_id']];

// Si c'est un format (Vin)
if ($row['type'] === 'format') {
$realCartonsCount += $qty; // Pour l'affichage UX uniquement

$volumeML = $row['volume_ml'];
if ($volumeML > 0) {
// Volume total de cette ligne en litres
$volumePerCarton = ($row['bottles_per_carton'] * $volumeML) / 1000;
$totalVolumeLiters += $qty * $volumePerCarton;
}
}
// Si c'est un accessoire (Produit simple)
else {
// ICI LA RÈGLE : 1 Accessoire = 1 Carton
// Si tu commandes 5 unités, ça ajoute 5 au total des cartons
$accessoriesCartons += $qty;
}
}

// 1. Calcul des cartons issus du vin (Base volume 4.5L)
$baseCartonVolume = 4.5;
$wineCartons = $totalVolumeLiters > 0
? (int) ceil($totalVolumeLiters / $baseCartonVolume)
: 0;

// 2. Total des cartons pour le shipping = Cartons Vin + Cartons Accessoires
$totalCartonsForShipping = $wineCartons + $accessoriesCartons;

/* =========================
RÉCUPÉRATION SEUIL FRANCO
========================= */
$stmt = $pdo->prepare("SELECT config_value FROM shipping_config WHERE config_key = 'FREE_SHIPPING_TOTAL'");
$stmt->execute();
$freeShippingThreshold = (float)$stmt->fetchColumn();
/* =========================
CALCUL LIVRAISON
========================= */
$shipping = resolveShippingRule($pdo, [
'total_ht' => $total,
'total_cartons' => $totalCartonsForShipping,
'country_code' => $_SESSION['country_code'] ?? 'FR'
]);
// Snapshot contractuel
$_SESSION['shipping'] = [
'rule_id' => $shipping['rule_id'],
'rule_code' => $shipping['rule_code'],
'label' => $shipping['label'],
'amount_ht' => (float)$shipping['amount_ht'],
'amount_ttc' => (float)($shipping['amount_ttc'] ?? $shipping['amount_ht']),
'vat_rate' => (int)($shipping['vat_rate'] ?? 0),
'country_code' => $_SESSION['country_code'] ?? 'FR',
'is_free' => ((float)$shipping['amount_ht'] === 0.0),
];
$shipping_ht = (float)($shipping['amount_ht'] ?? 0);
$total_ht_products = $total;
$total_ht_with_shipping = $total_ht_products + $shipping_ht;
$_SESSION['order_totals'] = [
'products_ht' => $total_ht_products,
'shipping_ht' => $shipping_ht,
'total_ht' => $total_ht_with_shipping
];
/* =========================
INDICATEURS UX LIVRAISON
========================= */
if ($total >= $freeShippingThreshold) {
$shippingProgress = 100;
} else {
$shippingProgress = min(
99,
(int) floor(($total / $freeShippingThreshold) * 100)
);
}
$remainingAmount = max(0, $freeShippingThreshold - $total);
// Prix moyen par carton équivalent
$avgCartonHT = $totalCartonsForShipping > 0
? ($total / $totalCartonsForShipping)
: 0;
// Volume restant pour le franco
$remainingVolume = $avgCartonHT > 0
? ($remainingAmount / $avgCartonHT) * $baseCartonVolume
: 0;
?>
<div class="cart-summary">
<div class="shipping-block" id="shipping-block">
<div class="shipping-progress">
<div class="bar">
<div
class="fill"
id="shipping-progress-bar"
style="width: <?= $shippingProgress ?>%">
</div>
</div>
<div class="progress-label">
<?= $shippingProgress ?> % – <?= t('shipping_goal_free') ?>
</div>
</div>
<div class="shipping-line">
<span class="label">
<?= t('shipping_title') ?>
</span>
<span class="price" id="shipping-amount">
<?= number_format($shipping['amount_ht'], 2, ',', ' ') ?> €
</span>
</div>
<div class="shipping-hint" id="shipping-hint">
<?php if ($shipping['amount_ht'] == 0): ?>
<strong>🎉 <?= t('shipping_free') ?></strong>
<?php else: ?>
<?= sprintf(
t('shipping_hint_amount'),
number_format($remainingAmount, 0, ',', ' '),
number_format($shipping['amount_ht'], 0, ',', ' ')
) ?>
<?php endif; ?>
</div>
</div>
<div class="total">
<span><?= t('Total HT') ?></span>
<strong id="cart-total"><?= number_format($total_ht_with_shipping, 2, ',', ' ') ?> €</strong>
</div>
<div class="cart-buttons">
<a class="btn" href="<?= url('checkout.php') ?>"><?= t('Valider la commande') ?></a>
</div>
</div>
</div>
<script>
/* =========================================================
AJAX – MISE À JOUR AUTOMATIQUE DES QUANTITÉS PRODUITS
========================================================= */
document.querySelectorAll('.qty-input').forEach(input => {
input.addEventListener('change', function () {
const id = this.dataset.id;
const qty = parseInt(this.value, 10) || 0;
fetch('<?= BASE_URL ?>update_cart_ajax.php', {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify({
format_id: id,
qty: qty,
})
})
.then(res => res.json())
.then(data => {
console.log('mise a jour panier', data);
if (!data.success) {
location.reload();
return;
}
if (qty <= 0) {
const rowEl = document.getElementById('row-' + id);
if (rowEl) rowEl.remove();
}
// Panier vide côté serveur → reload TOTAL
if (typeof data.cart_items_count !== 'undefined' && data.cart_items_count === 0) {
location.reload();
return;
}
else {
// Mise à jour du total de ligne
const lineEl = document.getElementById('line-' + id);
if (lineEl && data.line_total) {
lineEl.innerText = data.line_total;
}
// Mise à jour des infos
if (data.info_html) {
const infoEl = document.getElementById('info-' + id);
if (infoEl) {
infoEl.innerHTML = data.info_html;
}
}
// Mise à jour du prix unitaire
if (data.unit_price) {
const priceEl = document.getElementById('price-' + id);
if (priceEl) {
priceEl.innerText = data.unit_price;
}
}
}
// Mise à jour du total panier
const cartTotalEl = document.getElementById('cart-total');
if (cartTotalEl && data.cart_total) {
cartTotalEl.innerText = data.cart_total;
}
// Recharger les cadeaux
reloadGifts();
/* =========================================================
FRAIS DE LIVRAISON DYNAMIQUE
========================================================= */
if (data.shipping) {
// Montant livraison
const shippingAmountEl = document.getElementById('shipping-amount');
if (shippingAmountEl && data.shipping.amount) {
shippingAmountEl.innerText = data.shipping.amount;
}
// Barre de progression
const bar = document.getElementById('shipping-progress-bar');
if (bar && typeof data.shipping.progress !== 'undefined') {
const progress = Math.max(0, Math.min(100, data.shipping.progress));
bar.style.width = progress + '%';
}
// Label progression - CORRECTION ICI
const label = document.querySelector('.progress-label');
if (label && typeof data.shipping.progress !== 'undefined') {
const currentText = label.innerText || '';
const baseText = currentText.includes('–')
? currentText.split('–')[1].trim()
: <?= json_encode(t('shipping_goal_free')) ?>;
label.innerText = data.shipping.progress + ' % – ' + baseText;
}
// Message hint
const hint = document.getElementById('shipping-hint');
if (hint) {
if (data.shipping.is_free) {
hint.innerHTML = '<strong>🎉 ' + <?= json_encode(t('shipping_free')) ?> + '</strong>';
} else if (data.shipping.remaining_amount && data.shipping.current_cost) {
const hintTemplate = <?= json_encode(t('shipping_hint_amount')) ?>;
hint.innerHTML = hintTemplate
.replace('%s', data.shipping.remaining_amount)
.replace('%s', data.shipping.current_cost);
}
}
}
// Badge panier
const badge = document.getElementById('cart-badge');
if (badge && typeof data.cart_items_count !== 'undefined') {
if (data.cart_items_count > 0) {
badge.textContent = data.cart_items_count;
badge.style.display = 'inline-flex';
} else {
badge.style.display = 'none';
}
}
})
.catch(err => {
console.error('Erreur mise à jour panier: erreur réseau ou JSON malformé', err);
});
});
});

/* =========================================================
RECHARGEMENT COMPLET DES CADEAUX (HTML depuis PHP)
========================================================= */
function reloadGifts() {
const container = document.getElementById('cart-gifts');
if (!container) return;

fetch('<?= BASE_URL ?>get_gifts_ajax.php', { credentials: 'same-origin' })
.then(res => res.text())
.then(html => {
container.innerHTML = html;
bindGiftEvents();
validateGifts();
})
.catch(err => console.error('Erreur rechargement cadeaux', err));
}

/* =========================================================
VALIDATION DES CADEAUX
========================================================= */
function validateGifts() {
let valid = true;
document.querySelectorAll('.gift-item').forEach(item => {
const required = parseInt(item.dataset.required || 0, 10);
const selected = item.querySelectorAll(
'select.gift-choice option:checked:not([value=""])'
).length;
if (selected < required) {
valid = false;
}
});
window.giftsValidated = valid;
return valid;
}

/* =========================================================
POPUP BLOQUANTE SI CADEAUX MANQUANTS
========================================================= */
function showGiftAlert() {
if (document.querySelector('.gift-alert-overlay')) return;
const overlay = document.createElement('div');
overlay.className = 'gift-alert-overlay';
overlay.innerHTML = `
<div class="gift-alert-box">
<h3>🎁 <?= t("Cadeaux non sélectionnés") ?></h3>
<p><?= t("Veuillez choisir vos cadeaux avant de continuer") ?></p>
<button class="btn"><?= t("Compris") ?></button>
</div>
`;
document.body.appendChild(overlay);
document.body.style.overflow = 'hidden';
overlay.addEventListener('click', e => {
if (e.target === overlay || e.target.classList.contains('btn')) {
overlay.remove();
document.body.style.overflow = '';
}
});
}

/* =========================================================
INTERCEPTION DU CHECKOUT
========================================================= */
const checkoutBtn = document.querySelector('.btn[href*="checkout"]');
if (checkoutBtn) {
const href = checkoutBtn.getAttribute('href');
checkoutBtn.addEventListener('click', e => {
e.preventDefault();
e.stopPropagation();
if (validateGifts()) {
window.location.href = href;
} else {
showGiftAlert();
}
return false;
});
}

/* =========================================================
SAUVEGARDE DES CHOIX CADEAUX EN SESSION
========================================================= */
function saveGiftsToSession() {
const gifts = {};
document.querySelectorAll('.gift-choice').forEach(select => {
const type = select.dataset.giftType;
if (!gifts[type]) gifts[type] = [];
if (select.value) {
gifts[type].push(select.value);
}
});
fetch('<?= BASE_URL ?>save_gifts_ajax.php', {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify(gifts)
});
}

/* =========================================================
BIND DES EVENTS SUR SELECT CADEAUX
========================================================= */
function bindGiftEvents() {
document.querySelectorAll('.gift-choice').forEach(select => {
select.addEventListener('change', function () {
saveGiftsToSession();
validateGifts();
});
});
}

/* =========================================================
INIT
========================================================= */
bindGiftEvents();
validateGifts();
bindQtyStepper();
/* =========================================================
[-][+] INPUT CART.PHP
========================================================= */
function bindQtyStepper() {
document.querySelectorAll('.qty-stepper').forEach(stepper => {
const input = stepper.querySelector('.qty-input');

stepper.querySelectorAll('.qty-btn').forEach(btn => {
btn.addEventListener('click', () => {
let value = parseInt(input.value, 10) || 1;

if (btn.dataset.action === 'plus') {
value++;
}

if (btn.dataset.action === 'minus') {
value = Math.max(1, value); // ⛔ STOP À 1
value--;
value = Math.max(1, value);
}

input.value = value;
input.dispatchEvent(new Event('change', { bubbles: true }));
});
});
});
}
</script>
<?php 
// Calcul badge
$cartCount = count($_SESSION['cart'] ?? []);
$giftCount = 0;
foreach ($giftSummary as $gift) {
$giftCount += $gift['qty'];
}
$badgeCount = $cartCount + $giftCount;

// Stocker en session uniquement si panier non vide
if ($badgeCount > 0) {
$_SESSION['cart_gifts'] = $giftSummary;
} else {
unset($_SESSION['cart_gifts']); // nettoyage si vide
}
include __DIR__ . '/partials/footer.php'; ?>