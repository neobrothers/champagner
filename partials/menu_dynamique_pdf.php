<?php
// Détection langue
$lang = 'de';
if (strpos($_SERVER['REQUEST_URI'], '/fr/') !== false) $lang = 'fr';
elseif (strpos($_SERVER['REQUEST_URI'], '/en/') !== false) $lang = 'en';

// Base URL du site (ex: /monsite)
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Chemin PDF
$dir = __DIR__ . "/../pdf/$lang/";

// Récupération PDF
$pdfFiles = [];
if (is_dir($dir)) {
foreach (scandir($dir) as $file) {
if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
$pdfFiles[] = $file;
}
}
}

// Nettoyage noms
function cleanFileName($filename) {
return ucwords(str_replace('_', ' ', str_replace('.pdf', '', $filename)));
}
?>

<div class="pdf-menu-cinema-absolute">
<div class="pdf-menu-title">
<?= t('materials_info') ?>
<span class="pdf-menu-arrow">▼</span>
</div>
<div class="pdf-menu-dropdown">
<?php foreach ($pdfFiles as $pdf): ?>
<a href="<?php echo $baseUrl . "/pdf/$lang/$pdf"; ?>" target="_blank">
<?php echo cleanFileName($pdf); ?>
</a>
<?php endforeach; ?>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
const menu = document.querySelector('.pdf-menu-cinema-absolute');
const title = menu.querySelector('.pdf-menu-title');
const dropdown = menu.querySelector('.pdf-menu-dropdown');
const arrow = menu.querySelector('.pdf-menu-arrow');

// détection mobile réelle
const isMobile = window.matchMedia('(pointer: coarse)').matches;

if (!isMobile) return; // desktop = CSS hover existant

// ÉTAT INITIAL FORCÉ
menu.dataset.open = '0';
forceClose();

function forceOpen() {
dropdown.style.setProperty('opacity', '1', 'important');
dropdown.style.setProperty('transform', 'translateY(0)', 'important');
dropdown.style.setProperty('pointer-events', 'auto', 'important');
arrow.style.setProperty('transform', 'rotate(180deg)', 'important');
menu.dataset.open = '1';
}

function forceClose() {
dropdown.style.setProperty('opacity', '0', 'important');
dropdown.style.setProperty('transform', 'translateY(20px)', 'important');
dropdown.style.setProperty('pointer-events', 'none', 'important');
arrow.style.setProperty('transform', 'rotate(0deg)', 'important');
menu.dataset.open = '0';
}

function toggleMenu(e) {
e.preventDefault();
e.stopPropagation();
menu.dataset.open === '1' ? forceClose() : forceOpen();
}

// 1 clic sur le titre = toggle
title.addEventListener('click', toggleMenu);

// clic ailleurs = fermeture
document.addEventListener('click', forceClose);

// clic dans le menu = ne ferme pas
dropdown.addEventListener('click', e => e.stopPropagation());
});
</script>