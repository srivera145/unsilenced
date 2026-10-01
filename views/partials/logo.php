<?php
/**
 * The Unsilenced logo as inline SVG: a megaphone pointing up-right with three
 * sound waves, and the wordmark UNSILENCED. No image files.
 *
 * Set before requiring:
 *   $logoVariant  'horizontal' (mark and wordmark, the header) or 'icon' (mark
 *                 only: small screens). Default 'horizontal'.
 *   $logoClass    Extra classes for the <svg>, e.g. 'logo-wide'.
 *   $logoLabel    Accessible name. Default 'Unsilenced'. Pass null when the
 *                 surrounding link already carries the name; the SVG is then
 *                 hidden from assistive technology.
 *
 * Colour comes from CSS tokens, never from this file: the mark is
 * var(--logo-mark) through .logo-mark, the wordmark is currentColor, which
 * .logo sets to var(--logo-wordmark). Both switch with the theme in
 * public_html/css/keel.css. The wordmark is set in self-hosted Anton
 * (.logo-wordmark); textLength holds its width if the font has not loaded yet.
 *
 * public_html/favicon.svg is this mark with the colour written in, because a
 * favicon cannot read the page's CSS. Change the geometry in both places.
 */
$logoVariant = ($logoVariant ?? 'horizontal') === 'icon' ? 'icon' : 'horizontal';
$logoLabel = array_key_exists('logoLabel', get_defined_vars()) ? $logoLabel : 'Unsilenced';
$logoWidth = $logoVariant === 'icon' ? 64 : 268;
$logoClasses = trim('logo logo-' . $logoVariant . ' ' . ($logoClass ?? ''));
?>
<svg class="<?= htmlspecialchars($logoClasses, ENT_QUOTES, 'UTF-8') ?>" viewBox="0 0 <?= $logoWidth ?> 64" width="<?= $logoWidth ?>" height="64" focusable="false" <?= $logoLabel === null ? 'aria-hidden="true"' : 'role="img" aria-label="' . htmlspecialchars($logoLabel, ENT_QUOTES, 'UTF-8') . '"' ?>>
    <g class="logo-mark" transform="translate(30.92 34.41) scale(.9627) rotate(-38)">
        <g stroke-width="1" stroke-linejoin="round">
            <rect x="-4" y="-16" width="7" height="32" rx="3.5"/>
            <path d="M-26 -5.5 L-3 -12 L-3 12 L-26 5.5 Z"/>
            <rect x="-33" y="-5.5" width="8" height="11" rx="2.5"/>
            <rect x="-22" y="2" width="6.5" height="14.5" rx="2.5" transform="rotate(-8 -22 2)"/>
        </g>
        <g fill="none" stroke-width="4.8" stroke-linecap="round" transform="translate(4 0)">
            <path d="M7.49 -5.85 A9.5 9.5 0 0 1 7.49 5.85"/>
            <path d="M13.79 -10.77 A17.5 17.5 0 0 1 13.79 10.77"/>
            <path d="M20.09 -15.7 A25.5 25.5 0 0 1 20.09 15.7"/>
        </g>
    </g>
    <?php if ($logoVariant === 'horizontal'): ?>
    <text class="logo-wordmark" x="74" y="50" font-size="42" letter-spacing="1.2" textLength="193.3" lengthAdjust="spacingAndGlyphs" fill="currentColor">UNSILENCED</text>
    <?php endif; ?>
</svg>
<?php unset($logoVariant, $logoClass, $logoLabel, $logoWidth, $logoClasses); ?>
