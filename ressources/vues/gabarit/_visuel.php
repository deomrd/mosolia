<?php /** Cote visuel des pages de connexion : message, points forts et illustration dessinee (aucune image externe). */ ?>
<aside class="vitrine-visuel" aria-hidden="true">
  <div class="vitrine-texte">
    <p class="vitrine-sur"><?= e(t('vitrine.sur_titre')) ?></p>
    <p class="vitrine-titre"><?= e(t('vitrine.titre')) ?></p>
    <ul>
      <li><?= e(t('vitrine.point1')) ?></li>
      <li><?= e(t('vitrine.point2')) ?></li>
      <li><?= e(t('vitrine.point3')) ?></li>
    </ul>
  </div>
  <svg class="vitrine-dessin" viewBox="0 0 520 300" role="img">
    <rect x="0" y="0" width="520" height="300" rx="18" fill="#fff" opacity=".08"/>
    <rect x="24" y="24" width="150" height="74" rx="12" fill="#fff" opacity=".14"/>
    <rect x="40" y="42" width="64" height="8" rx="4" fill="#fff" opacity=".55"/>
    <rect x="40" y="62" width="104" height="16" rx="6" fill="#fff"/>
    <rect x="186" y="24" width="150" height="74" rx="12" fill="#fff" opacity=".14"/>
    <rect x="202" y="42" width="72" height="8" rx="4" fill="#fff" opacity=".55"/>
    <rect x="202" y="62" width="88" height="16" rx="6" fill="#fff"/>
    <rect x="348" y="24" width="148" height="74" rx="12" fill="#F4C95D"/>
    <rect x="364" y="42" width="60" height="8" rx="4" fill="#5C3B00" opacity=".6"/>
    <rect x="364" y="62" width="96" height="16" rx="6" fill="#5C3B00"/>
    <g fill="#fff">
      <rect x="40" y="214" width="26" height="54" rx="6" opacity=".35"/>
      <rect x="80" y="196" width="26" height="72" rx="6" opacity=".45"/>
      <rect x="120" y="206" width="26" height="62" rx="6" opacity=".45"/>
      <rect x="160" y="176" width="26" height="92" rx="6" opacity=".6"/>
      <rect x="200" y="186" width="26" height="82" rx="6" opacity=".6"/>
      <rect x="240" y="156" width="26" height="112" rx="6" opacity=".78"/>
      <rect x="280" y="166" width="26" height="102" rx="6" opacity=".78"/>
      <rect x="320" y="136" width="26" height="132" rx="6"/>
    </g>
    <path d="M40 190 C110 180 150 150 214 152 S300 112 346 104 S440 92 482 122" fill="none" stroke="#F4C95D" stroke-width="5" stroke-linecap="round"/>
    <circle cx="482" cy="122" r="8" fill="#F4C95D"/>
    <rect x="372" y="150" width="124" height="118" rx="12" fill="#fff" opacity=".14"/>
    <circle cx="398" cy="178" r="10" fill="#fff" opacity=".8"/>
    <rect x="416" y="172" width="62" height="10" rx="5" fill="#fff" opacity=".7"/>
    <circle cx="398" cy="210" r="10" fill="#fff" opacity=".5"/>
    <rect x="416" y="204" width="48" height="10" rx="5" fill="#fff" opacity=".5"/>
    <circle cx="398" cy="242" r="10" fill="#fff" opacity=".35"/>
    <rect x="416" y="236" width="56" height="10" rx="5" fill="#fff" opacity=".35"/>
  </svg>
</aside>
