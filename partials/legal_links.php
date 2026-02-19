<?php
require_once __DIR__ . '/../config.php';

// Récupérer CGV et mentions légales depuis la BDD
$stmt = $pdo->prepare("SELECT code, content_fr, content_de, content_en FROM documents WHERE code IN ('cgv','legal')");
$stmt->execute();
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contents = [];
$lang = APP_LANG ?? 'fr';
foreach($docs as $doc){
$contents[$doc['code']] = $doc["content_{$lang}"] ?? $doc['content_fr'] ?? '';
}

// Remplacer automatiquement les {{variables}}
$vars = $pdo->query("SELECT variable_key, value_{$lang} as value FROM document_variables")->fetchAll(PDO::FETCH_ASSOC);
foreach(['cgv','legal'] as $code){
foreach($vars as $v){
$val = $v['value'] ?? '';
$contents[$code] = str_replace('{{'.$v['variable_key'].'}}', $val, $contents[$code]);
}
}

$cgv_content = htmlspecialchars($contents['cgv'] ?? '');
$legal_content = htmlspecialchars($contents['legal'] ?? '');
?>

<div id="legal-slide">
<div class="legal-arrow">➤</div>
<div class="legal-links">
<a href="#" class="legal-link" data-content="<?= $cgv_content ?>"><?= t('Conditions générales de vente') ?></a>
<span class="sep">|</span>
<a href="#" class="legal-link" data-content="<?= $legal_content ?>"><?= t('Mentions légales') ?></a>
</div>
</div>

<div id="legal-popup" class="legal-popup">
<div class="legal-popup-content">
<span class="legal-close">&times;</span>
<div class="legal-popup-text"></div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
const links = document.querySelectorAll('#legal-slide .legal-link');
const popup = document.getElementById('legal-popup');
const popupText = popup.querySelector('.legal-popup-text');
const closeBtn = popup.querySelector('.legal-close');

links.forEach(link => {
link.addEventListener('click', function(e){
e.preventDefault();
// Remplacer \r\n par <br> pour le HTML
popupText.innerHTML = this.dataset.content.replace(/\r\n/g, "<br>").replace(/\n/g,"<br>");
popup.classList.add('show');
});
});

closeBtn.addEventListener('click', function(){
popup.classList.remove('show');
});

popup.addEventListener('click', function(e){
if(e.target === popup) popup.classList.remove('show');
});
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
const slide = document.getElementById('legal-slide');
const arrow = slide.querySelector('.legal-arrow');
let timer = null;

const isMobile = () => window.matchMedia('(hover: none)').matches;

arrow.addEventListener('click', (e) => {
if (!isMobile()) return;

e.preventDefault();
slide.classList.add('open');

clearTimeout(timer);
timer = setTimeout(() => {
slide.classList.remove('open');
}, 4000);
});

// sécurité : ferme si on touche ailleurs
document.addEventListener('touchstart', (e) => {
if (!slide.contains(e.target)) {
slide.classList.remove('open');
clearTimeout(timer);
}
});
});

/* === POPUP (inchangé) === */
const links = document.querySelectorAll('#legal-slide .legal-link');
const popup = document.getElementById('legal-popup');
const popupText = popup.querySelector('.legal-popup-text');
const closeBtn = popup.querySelector('.legal-close');

links.forEach(link => {
link.addEventListener('click', function(e){
e.preventDefault();
popupText.innerHTML = this.dataset.content
.replace(/\r\n/g, "<br>")
.replace(/\n/g,"<br>");
popup.classList.add('show');
});
});

closeBtn.addEventListener('click', function(){
popup.classList.remove('show');
});

popup.addEventListener('click', function(e){
if(e.target === popup) popup.classList.remove('show');
});
</script>