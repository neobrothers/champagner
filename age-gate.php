<?php
// ===============================
// AGE GATE – BLOQUANT & PREMIUM
// ===============================

$cookieName = 'age_verified';

// Détection langue simple (URL ou constante)
$lang = 'de';

if (isset($_SERVER['REQUEST_URI'])) {
if (str_starts_with($_SERVER['REQUEST_URI'], '/fr')) {
$lang = 'fr';
} elseif (str_starts_with($_SERVER['REQUEST_URI'], '/en')) {
$lang = 'en';
} elseif (str_starts_with($_SERVER['REQUEST_URI'], '/de')) {
$lang = 'de';
}
}

// Textes
$texts = [
'fr' => [
'title' => 'Vérification de l’âge',
'text' => 'Ce site est strictement réservé aux personnes ayant atteint l’âge légal requis pour la consommation de boissons alcoolisées. En confirmant l’accès, vous déclarez avoir au moins 18 ans.',
'yes' => 'Oui, j’ai au moins 18 ans',
'no' => 'Quitter le site'
],
'de' => [
'title' => 'Altersbestätigung',
'text' => 'Der Zugang zu dieser Website ist ausschließlich Personen vorbehalten, die das gesetzliche Mindestalter für den Konsum alkoholischer Getränke erreicht haben. Bitte bestätigen Sie, dass Sie mindestens 18 Jahre alt sind.',
'yes' => 'Ja, ich bin mindestens 18 Jahre alt',
'no' => 'Website verlassen'
],
'en' => [
'title' => 'Age Verification',
'text' => 'This website is strictly intended for individuals who have reached the legal age required to consume alcoholic beverages. By confirming access, you declare that you are at least 18 years old.',
'yes' => 'Yes, I am at least 18 years old',
'no' => 'Leave website'
],
];

// Si déjà confirmé → ne rien afficher
if (isset($_COOKIE[$cookieName])) {
return;
}

$t = $texts[$lang];
?>

<div id="age-gate">
<div class="age-box">
<h1><?= htmlspecialchars($t['title']) ?></h1>
<p><?= htmlspecialchars($t['text']) ?></p>

<form method="post" class="age-actions">
<button type="submit" name="confirm_age" class="age-yes">
<?= htmlspecialchars($t['yes']) ?>
</button>
<a href="https://www.google.com" class="age-no">
<?= htmlspecialchars($t['no']) ?>
</a>
</form>
</div>
</div>