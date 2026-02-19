</div>

<footer class="bottom-menu">

<a href="<?= url('index.php') ?>" class="menu-item catalogue" data-page="index.php">
<!-- Catalogue -->
<div class="icon-wrap">
<!-- point d'ancrage bulles -->
<span class="bubble-anchor"></span>
<!-- SVG -->
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<path d="M7 3v3c0 1-1 2-1 3v10a2 2 0 0 0 4 0V9c0-1-1-2-1-3V3"/>
<path d="M14 6c2 0 3 1.5 3 3v3c0 1.5-1 3-3 3s-3-1.5-3-3V9c0-1.5 1-3 3-3z"/>
<path d="M14 15v4"/>
</svg>
</div>
<span><?= t('Catalogue') ?></span>
</a>

<!-- Commander -->
<?php
// Sécurité session et tableau
$cartCount = count($_SESSION['cart'] ?? []);
$giftCount = 0;
if (!empty($_SESSION['cart_gifts'])) {
foreach ($_SESSION['cart_gifts'] as $gift) {
$giftCount += $gift['qty'];
}
}
$badgeCount = $cartCount + $giftCount;
?>
<a href="<?= url('cart.php') ?>" class="menu-item" data-page="cart.php">
<div class="icon-wrap-pop">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<circle cx="9" cy="21" r="1"/>
<circle cx="20" cy="21" r="1"/>
<path d="M1 1h4l2.5 13h11l2-8H6"/>
</svg>
<?php if ($badgeCount > 0): ?>
<span class="cart-badge-pop" id="cart-badge">
<?= $badgeCount ?>
</span>
<?php endif; ?>
</div>
<span><?= t('Commander') ?></span>
</a>

<script>
// === Mise à jour dynamique du badge panier ===
function updateCartBadge(count){
let badge = document.getElementById('cart-badge');
if(count > 0){
if(!badge){
// Crée le badge s'il n'existe pas
badge = document.createElement('span');
badge.id = 'cart-badge';
badge.className = 'cart-badge-pop';
badge.textContent = count;
document.querySelector('.icon-wrap-pop').appendChild(badge);
} else {
badge.textContent = count;
}
} else {
// Supprime le badge si le panier est vide
if(badge) badge.remove();
}
}

// Exemple : si tu mets à jour via AJAX
// updateCartBadge(nouvelleValeur);
</script>

<a href="<?= url('my_orders.php') ?>" class="menu-item" data-page="my_orders.php">
<!-- Mes commandes -->
<div class="icon-wrap">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
<path d="M3.27 6.96L12 12l8.73-5.04"/>
<path d="M12 22V12"/>
</svg>
</div>
<span><?= t('Mes commandes') ?></span>
</a>

<a href="<?= url('my_account.php') ?>" class="menu-item" data-page="my_account.php">
<!-- Mon compte -->
<div class="icon-wrap">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<circle cx="12" cy="8" r="4"/>
<path d="M4 22c0-4 4-6 8-6s8 2 8 6"/>
</svg>
</div>
<span><?= t('Mon compte') ?></span>
</a>

</footer>
<?php include __DIR__ . '/../includes/info_bulles_loader.php'; ?>
<script>
// === MISE À JOUR PANIER AJAX ===
document.querySelectorAll('.qty-input').forEach(input => {
input.addEventListener('change', function () {
const formatId = this.dataset.id;
const qty = this.value;

fetch('<?= BASE_URL ?>update_cart_ajax.php', {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify({
format_id: formatId,
qty: qty
})
})
.then(res => res.json())
.then(data => {
if (data.success) {
document.getElementById('line-' + formatId).innerText = data.line_total;
document.getElementById('bottles-' + formatId).innerText =
data.total_bottles + ' <?= t('bouteilles au total') ?>';
document.getElementById('cart-total').innerText = data.cart_total;
} else {
location.reload();
}
})
.catch(err => {
console.error('Erreur mise à jour panier:', err);
location.reload();
});
});
});

// === DÉTECTION PAGE ACTIVE ===
const currentPath = window.location.pathname;
const currentPage = currentPath.split('/').pop();

document.querySelectorAll('.menu-item').forEach(link => {
const linkPage = link.getAttribute('data-page');

if (linkPage === currentPage) {
link.classList.add('active');
}
});
</script>
<script>
(function () {
const slide = document.getElementById('legal-slide');
const toggle = slide.querySelector('.legal-toggle');

let startX = 0;
let currentX = 0;
let isTouching = false;

// Toggle au clic
toggle.addEventListener('click', () => {
slide.classList.toggle('open');
});

// --- SWIPE MOBILE ---
slide.addEventListener('touchstart', (e) => {
startX = e.touches[0].clientX;
isTouching = true;
}, { passive: true });

slide.addEventListener('touchmove', (e) => {
if (!isTouching) return;
currentX = e.touches[0].clientX;
}, { passive: true });

slide.addEventListener('touchend', () => {
if (!isTouching) return;

const diff = currentX - startX;

// swipe vers la gauche → ouvrir
if (diff < -40) {
slide.classList.add('open');
}

// swipe vers la droite → fermer
if (diff > 40) {
slide.classList.remove('open');
}

isTouching = false;
startX = 0;
currentX = 0;
});
})();
</script>
<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>public/js/tracker.js" defer></script>
</body>
</html>