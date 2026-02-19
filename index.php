<?php
require_once __DIR__ . '/config.php';
include __DIR__ . '/partials/header.php';
error_reporting(E_ALL & ~E_DEPRECATED);

// Langue active
$lang = in_array(APP_LANG, ['fr', 'de', 'en']) ? APP_LANG : 'de';

// Charger fichier de langue
$langFile = __DIR__ . "/lang/$lang.php";
$translations = file_exists($langFile) ? include $langFile : [];

// Récupérer les produits avec leur image catalogue et la collection
$stmt = $pdo->prepare("
SELECT
p.id,
COALESCE(NULLIF(pt.name, ''), NULLIF(pt_fr.name, ''), p.name) AS name,
COALESCE(NULLIF(pt.subtitle, ''), NULLIF(pt_fr.subtitle, ''), p.subtitle) AS subtitle,
pt.slug AS slug_current,
pt_de.slug AS slug_de,
pt_fr.slug AS slug_fr,
pt_en.slug AS slug_en,
p.image AS fallback_image,
pi.image AS catalog_image,
p.collection
FROM products p
LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = :lang
LEFT JOIN product_translations pt_de ON pt_de.product_id = p.id AND pt_de.lang = 'de'
LEFT JOIN product_translations pt_fr ON pt_fr.product_id = p.id AND pt_fr.lang = 'fr'
LEFT JOIN product_translations pt_en ON pt_en.product_id = p.id AND pt_en.lang = 'en'
LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_catalog = 1
WHERE p.is_active = 1
ORDER BY FIELD(p.collection, 'BRUT','PRESTIGE','EXTRA BRUT','ACCESSOIRES'), p.display_order ASC, p.created_at DESC
");
$stmt->execute(['lang' => $lang]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les labels produits avec leurs textes
$stmt = $pdo->prepare("
SELECT l.*, pll.product_id
FROM product_label_link pll
JOIN product_labels l ON l.id = pll.label_id
WHERE pll.product_id IN (".implode(',', array_column($products,'id')).")
ORDER BY pll.position ASC
");
$stmt->execute();

$labelsByProduct = [];
foreach ($stmt->fetchAll() as $row) {
$labelsByProduct[$row['product_id']][] = $row;
}
?>

<div class="products">
<?php
$lastCollection = '';
foreach ($products as $p):
if ($p['collection'] !== $lastCollection):
$lastCollection = $p['collection'];

// Titre avec traduction
switch($lastCollection){
case 'BRUT':
$title = $translations['collection_brut'] ?? 'COLLECTION BRUT';
break;
case 'PRESTIGE':
$title = $translations['collection_prestige'] ?? 'COLLECTION PRESTIGE';
break;
case 'EXTRA BRUT':
$title = $translations['collection_extra_brut'] ?? 'COLLECTION EXTRA BRUT';
break;
case 'ACCESSOIRES':
$title = $translations['accessoires'] ?? 'ACCESSOIRES';
break;
default:
$title = $lastCollection;
}

echo '<h1 class="collection-title">'.htmlspecialchars($title).'</h1>';
endif;

$displayImage = $p['catalog_image'] ?? $p['fallback_image'];

$slugProduit = $p['slug_current'] ?: $p['slug_de'] ?: $p['slug_fr'] ?: $p['slug_en'] ?: null;
$lienProduit = $slugProduit ? BASE_URL . $lang . '/' . $slugProduit : url('product.php?id=' . $p['id']);
?>
<a href="<?= $lienProduit ?>" class="product-card">
<div class="product-card-inner">
<div class="product-image">
<?php if ($displayImage): ?>
<img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($displayImage) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
<?php endif; ?>
</div>
<div class="product-info">
<h2><?= htmlspecialchars($p['name']) ?></h2>
<p class="subtitle"><?= htmlspecialchars($p['subtitle']) ?></p>
<div class="subtitle-line"></div>
</div>

<?php if (!empty($labelsByProduct[$p['id']])): ?>
<div class="product-labels">
<?php foreach ($labelsByProduct[$p['id']] as $l): ?>
<span class="product-label">
<?= htmlspecialchars($l['text_'.$lang]) ?>
</span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</a>
<?php endforeach; ?>
</div>
<div id="champagne-trigger">🥂</div>
<div id="champagne-overlay">
<div class="champagne-diapo">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_31.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_1.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_2.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_3.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_4.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_5.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_6.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_7.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_8.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_9.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_10.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_11.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_12.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_13.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_14.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_15.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_16.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_17.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_18.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_19.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_20.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_21.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_22.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_23.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_24.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_25.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_26.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_27.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_28.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_29.jpg" alt="champagne Gardet depuis 1895">
<img src="<?= BASE_URL ?>uploads/carroussel/champagne_gardet_30.jpg" alt="champagne Gardet depuis 1895">
</div>
</div>

<script>
const trigger = document.getElementById('champagne-trigger');
const overlay = document.getElementById('champagne-overlay');
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