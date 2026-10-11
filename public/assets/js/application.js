// AvecXY - comportements de l'interface. Aucune dependance.
(function () {
  'use strict';

  // Le stockage du navigateur peut etre indisponible (navigation privee) : on s'en passe alors.
  var memoire = {
    lire: function (cle) { try { return window.localStorage.getItem(cle); } catch (e) { return null; } },
    ecrire: function (cle, valeur) { try { window.localStorage.setItem(cle, valeur); } catch (e) { /* ignore */ } }
  };

  // --- Menu accordeon : chaque groupe s'ouvre et se ferme au clic, l'etat est memorise ---
  document.querySelectorAll('.groupe').forEach(function (groupe) {
    var titre = groupe.querySelector('.groupe-titre');
    var corps = groupe.querySelector('.groupe-corps');
    var cle = 'avecxy.menu.' + groupe.dataset.groupe;
    var regler = function (ouvert) {
      titre.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      corps.hidden = !ouvert;
    };
    var memorise = memoire.lire(cle);
    // Le groupe de la page en cours reste toujours ouvert a l'arrivee.
    if (!groupe.hasAttribute('data-actif') && memorise !== null) {
      regler(memorise === '1');
    }
    titre.addEventListener('click', function () {
      var ouvert = titre.getAttribute('aria-expanded') !== 'true';
      regler(ouvert);
      memoire.ecrire(cle, ouvert ? '1' : '0');
    });
  });

  // --- Menu entier : repliable sur ordinateur, tiroir sur telephone ---
  var ouvrir = document.querySelector('[data-menu-ouvrir]');
  var estTelephone = function () { return window.matchMedia('(max-width: 860px)').matches; };
  if (memoire.lire('avecxy.menu.replie') === '1') { document.body.classList.add('menu-replie'); }
  var fermerTiroir = function () {
    document.body.classList.remove('menu-ouvert');
    if (ouvrir) { ouvrir.setAttribute('aria-expanded', 'false'); }
  };
  if (ouvrir) {
    ouvrir.addEventListener('click', function () {
      if (estTelephone()) {
        var ouvert = document.body.classList.toggle('menu-ouvert');
        ouvrir.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      } else {
        var replie = document.body.classList.toggle('menu-replie');
        memoire.ecrire('avecxy.menu.replie', replie ? '1' : '0');
      }
    });
  }
  document.querySelectorAll('[data-menu-fermer]').forEach(function (el) { el.addEventListener('click', fermerTiroir); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermerTiroir(); } });

  // --- Noms : MAJUSCULES sans accents pendant la saisie (le serveur refait la meme chose) ---
  var sansAccents = function (texte) {
    return texte.toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/Æ/g, 'AE').replace(/Œ/g, 'OE').replace(/Ø/g, 'O');
  };
  document.querySelectorAll('input[data-majuscules]').forEach(function (champ) {
    champ.addEventListener('input', function () {
      var debut = champ.selectionStart, fin = champ.selectionEnd;
      var propre = sansAccents(champ.value);
      if (propre !== champ.value) {
        var memeLongueur = propre.length === champ.value.length;
        champ.value = propre;
        if (memeLongueur) { champ.setSelectionRange(debut, fin); }
      }
    });
  });

  // --- Confirmation avant une action sensible ---
  document.querySelectorAll('form[data-confirmer]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (e) {
      if (!window.confirm(formulaire.dataset.confirmer)) { e.preventDefault(); }
    });
  });

  // --- Anti double envoi : un formulaire ne part qu'une fois ---
  document.querySelectorAll('form[method="post"]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function (e) {
      if (e.defaultPrevented) { return; }
      if (formulaire.dataset.envoye === '1') { e.preventDefault(); return; }
      formulaire.dataset.envoye = '1';
    });
  });

  // --- Profils : tout cocher / tout decocher un module ---
  document.querySelectorAll('[data-tout-cocher]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var cases = bouton.closest('.carte').querySelectorAll('input[type=checkbox]');
      var cocher = Array.prototype.some.call(cases, function (c) { return !c.checked; });
      cases.forEach(function (c) { c.checked = cocher; });
    });
  });

  // --- Utilisateur : couleur des regles personnelles ---
  document.querySelectorAll('select[data-regle]').forEach(function (liste) {
    var colorer = function () {
      liste.classList.toggle('regle-accorde', liste.value === 'ACCORDE');
      liste.classList.toggle('regle-refuse', liste.value === 'REFUSE');
    };
    liste.addEventListener('change', colorer);
    colorer();
  });

  // --- Utilisateur : "Selon le profil" indique ce que le profil choisi donne (oui / non) ---
  var formulaire = document.querySelector('form[data-droits-profils]');
  var profil = document.querySelector('select[data-profil]');
  if (formulaire && profil) {
    var parProfil = JSON.parse(formulaire.dataset.droitsProfils || '{}');
    var actualiser = function () {
      var droits = parProfil[profil.value] || [];
      formulaire.querySelectorAll('select[data-regle]').forEach(function (liste) {
        var oui = droits.indexOf(liste.dataset.regle) !== -1;
        liste.options[0].textContent = profil.value === '' ? liste.options[0].textContent
          : (oui ? formulaire.dataset.texteOui : formulaire.dataset.texteNon);
      });
    };
    profil.addEventListener('change', actualiser);
    actualiser();
  }

  // --- Type de carnet : champs selon le mode, et apercu de la regle de benefice ---
  var typeCarnet = document.querySelector('form[data-type-carnet]');
  if (typeCarnet) {
    var champ = function (nom) { return typeCarnet.querySelector('[name="' + nom + '"]'); };
    var mode = function () { var c = typeCarnet.querySelector('[name=mode]:checked'); return c ? c.value : 'PARTS'; };
    var limiter = function (liste, permises) {
      Array.prototype.forEach.call(liste.options, function (o) { o.hidden = o.disabled = permises.indexOf(o.value) === -1; });
      if (permises.indexOf(liste.value) === -1) { liste.value = permises[0]; }
    };
    var actualiserType = function () {
      var m = mode();
      var retrait = champ('retrait').value;
      typeCarnet.querySelectorAll('[data-si-mode]').forEach(function (el) {
        el.hidden = el.dataset.siMode.split(' ').indexOf(m) === -1 || (el.dataset.siRetrait !== undefined && el.dataset.siRetrait !== retrait);
      });
      var anticipe = champ('retrait_anticipe');
      typeCarnet.querySelectorAll('[data-si-anticipe]').forEach(function (el) { el.hidden = !anticipe || el.dataset.siAnticipe !== anticipe.value; });
      var frais = champ('frais_tenue_type').value;
      typeCarnet.querySelectorAll('[data-si-frais]').forEach(function (el) { el.hidden = frais === 'AUCUN'; });
      // options coherentes entre elles (le serveur refait les memes controles)
      if (m !== 'PARTS') { limiter(champ('fermeture'), ['MANUELLE', 'ECHEANCE']); }
      else if (retrait === 'TOTAL') { limiter(champ('fermeture'), ['PREMIER_RETRAIT']); }
      else if (retrait === 'PAR_TRANCHE') { limiter(champ('fermeture'), ['DERNIERE_TRANCHE_RETIREE', 'PREMIER_RETRAIT', 'ECHEANCE', 'MANUELLE']); }
      else { limiter(champ('fermeture'), ['ECHEANCE', 'MANUELLE', 'PREMIER_RETRAIT']); }
      limiter(champ('frais_tenue_frequence'), frais === 'POURCENTAGE_RETRAIT' ? ['A_CHAQUE_RETRAIT'] : ['MENSUEL', 'A_CHAQUE_RETRAIT', 'A_LA_CLOTURE']);

      var zone = typeCarnet.querySelector('[data-apercu-texte]');
      var parts = parseInt(champ('nombre_parts').value, 10) || 0;
      var tranche = parseInt(champ('taille_tranche').value, 10) || parts;
      var par = parseInt(champ('parts_benefice_par_tranche').value, 10) || 0;
      if (m !== 'PARTS' || parts < 1 || tranche < 1 || tranche > parts || par >= tranche) { zone.textContent = ''; return; }
      if (par < 1) { zone.textContent = typeCarnet.dataset.apercuAucun; return; }
      var fin = champ('prelevement_benefice').value === 'FIN_TRANCHE', numeros = [];
      for (var debut = 1; debut <= parts; debut += tranche) {
        var dernier = Math.min(debut + tranche - 1, parts);
        for (var i = 0; i < par; i++) {
          var n = fin ? dernier - i : debut + i;
          if (n >= debut && n <= dernier) { numeros.push(n); }
        }
      }
      numeros.sort(function (a, b) { return a - b; });
      var liste = numeros.length > 14 ? numeros.slice(0, 14).join(', ') + '…' : numeros.join(', ');
      zone.textContent = typeCarnet.dataset.apercu
        .replace(':tranches', Math.ceil(parts / tranche)).replace(':taille', tranche)
        .replace(':numeros', liste).replace(':benefice', numeros.length).replace(':epargne', parts - numeros.length);
    };
    typeCarnet.addEventListener('input', actualiserType);
    typeCarnet.addEventListener('change', actualiserType);
    // modeles : remplissent le formulaire, l'administrateur ajuste ensuite
    typeCarnet.querySelectorAll('[data-modele]').forEach(function (bouton) {
      bouton.addEventListener('click', function () {
        var valeurs = JSON.parse(bouton.dataset.modele);
        Object.keys(valeurs).forEach(function (nom) {
          if (nom === 'mode') {
            var radio = typeCarnet.querySelector('[name=mode][value="' + valeurs.mode + '"]');
            if (radio) { radio.checked = true; }
          } else if (champ(nom)) {
            champ(nom).value = valeurs[nom];
          }
        });
        typeCarnet.querySelectorAll('[data-modele]').forEach(function (b) { b.classList.toggle('choisi', b === bouton); });
        actualiserType();
        champ('nom').focus();
      });
    });
    actualiserType();
  }

  // --- Ecriture comptable : total des debits et des credits pendant la saisie ---
  var ecriture = document.querySelector('form[data-ecriture]');
  if (ecriture) {
    var lireMontant = function (v) { return Math.round((parseFloat(String(v).replace(/\s/g, '').replace(',', '.')) || 0) * 100); };
    var equilibrer = function () {
      var d = 0, c = 0;
      ecriture.querySelectorAll('[data-debit]').forEach(function (x) { d += lireMontant(x.value); });
      ecriture.querySelectorAll('[data-credit]').forEach(function (x) { c += lireMontant(x.value); });
      var zone = ecriture.querySelector('[data-equilibre]');
      var format = function (n) { return (n / 100).toLocaleString('fr-FR'); };
      if (!d && !c) { zone.textContent = ''; return; }
      zone.textContent = d === c ? ecriture.dataset.messageOk.replace(':montant', format(d))
        : ecriture.dataset.messageEcart.replace(':debit', format(d)).replace(':credit', format(c));
      zone.classList.toggle('erreur-texte', d !== c);
    };
    ecriture.addEventListener('input', equilibrer);
  }

  // --- Tableaux sur telephone : seules les colonnes essentielles restent (en-tetes marques tel-cache) ---
  var telephone = window.matchMedia('(max-width: 640px)');
  var alleger = function () {
    document.querySelectorAll('table').forEach(function (table) {
      var entete = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
      if (!entete) { return; }
      var caches = [], col = 0;
      Array.prototype.forEach.call(entete.cells, function (th) {
        var n = th.colSpan || 1;
        if (th.classList.contains('tel-cache')) { for (var i = 0; i < n; i++) { caches.push(col + i); } }
        col += n;
      });
      if (!caches.length) { return; }
      table.classList.toggle('allegee', telephone.matches);
      Array.prototype.forEach.call(table.rows, function (ligne) {
        var position = 0;
        Array.prototype.forEach.call(ligne.cells, function (cellule) {
          if (cellule.dataset.colspan === undefined) { cellule.dataset.colspan = cellule.colSpan || 1; }
          var largeur = parseInt(cellule.dataset.colspan, 10), cachees = 0;
          for (var i = 0; i < largeur; i++) { if (caches.indexOf(position + i) !== -1) { cachees++; } }
          position += largeur;
          var petit = telephone.matches;
          cellule.classList.toggle('cellule-cachee', petit && cachees === largeur);
          if (largeur > 1) { cellule.colSpan = petit ? Math.max(1, largeur - cachees) : largeur; }
        });
      });
    });
  };
  alleger();
  if (telephone.addEventListener) { telephone.addEventListener('change', alleger); }
  // les listes filtrees en direct remplacent leur contenu : on rallege
  if (window.MutationObserver) {
    var enAttente = null;
    new MutationObserver(function () { clearTimeout(enAttente); enAttente = setTimeout(alleger, 50); }).observe(document.body, { childList: true, subtree: true });
  }

  // --- Photo du client : envoyee des qu'elle est choisie ---
  document.querySelectorAll('form[data-envoi-photo] input[type=file]').forEach(function (champ) {
    champ.addEventListener('change', function () { if (champ.files.length) { champ.form.submit(); } });
  });

  // --- Vente d'un carnet : les champs suivent le type choisi (a parts ou libre) ---
  var vente = document.querySelector('form[data-vente-carnet]');
  var typeChoisi = vente ? vente.querySelector('select[data-type]') : null;
  if (typeChoisi) {
    var actualiserVente = function () {
      var option = typeChoisi.options[typeChoisi.selectedIndex];
      var modeType = option ? option.dataset.mode : '';
      vente.querySelectorAll('[data-si-type]').forEach(function (el) {
        var liste = el.dataset.siType.split(' ');
        var visible = modeType !== '' && (liste.indexOf(modeType) !== -1 || liste.indexOf('T' + typeChoisi.value) !== -1);
        el.hidden = !visible;
        // un champ masque ne part pas avec le formulaire
        el.querySelectorAll('input, select').forEach(function (c) { c.disabled = !visible; });
      });
      var mise = vente.querySelector('[data-mise]');
      if (mise) { mise.required = modeType === 'PARTS'; }
      var prix = vente.querySelector('[data-prix-texte]');
      if (prix) { prix.textContent = modeType ? prix.dataset.modele.replace(':prix', option.dataset.prix) : ''; }
      var min = vente.querySelector('[data-min-texte]');
      if (min) { min.textContent = modeType === 'PARTS' && option.dataset.min ? min.dataset.modele.replace(':min', option.dataset.min) : ''; }
    };
    typeChoisi.addEventListener('change', actualiserVente);
    actualiserVente();
  }

  // --- Collecte du jour : boutons + et -, total en direct, recherche d'un client ---
  var collecte = document.querySelector('form[data-collecte]');
  if (collecte) {
    var zoneTotal = collecte.querySelector('[data-collecte-total]');
    var nombre = function (texte) { return parseFloat(String(texte).replace(/\s/g, '').replace(',', '.')) || 0; };
    var totaliser = function () {
      var total = 0;
      collecte.querySelectorAll('input[data-mise]').forEach(function (c) { total += (parseInt(c.value, 10) || 0) * parseInt(c.dataset.mise, 10); });
      collecte.querySelectorAll('input[data-libre]').forEach(function (c) { total += Math.round(nombre(c.value) * 100); });
      zoneTotal.textContent = (total / 100).toLocaleString('fr-FR') + ' ' + zoneTotal.dataset.symbole;
    };
    collecte.addEventListener('click', function (e) {
      var bouton = e.target.closest('[data-plus],[data-moins]');
      if (!bouton) { return; }
      var c = bouton.parentNode.querySelector('input');
      var valeur = (parseInt(c.value, 10) || 0) + (bouton.hasAttribute('data-plus') ? 1 : -1);
      valeur = Math.max(0, Math.min(parseInt(c.dataset.max, 10), valeur));
      c.value = valeur === 0 ? '' : valeur;
      totaliser();
    });
    collecte.addEventListener('input', function (e) {
      var c = e.target;
      if (c.matches('input[data-mise]') && c.value !== '') {
        c.value = Math.max(0, Math.min(parseInt(c.dataset.max, 10), parseInt(c.value, 10) || 0)) || '';
      }
      totaliser();
    });
    var filtre = collecte.querySelector('[data-collecte-filtre]');
    if (filtre) {
      filtre.addEventListener('input', function () {
        var q = sansAccents(filtre.value.trim());
        collecte.querySelectorAll('[data-ligne]').forEach(function (l) { l.hidden = q !== '' && l.dataset.texte.indexOf(q) === -1; });
      });
    }
    totaliser();
  }

  // --- Listes : filtre automatique pendant la saisie, sans bouton ---
  // Le formulaire [data-filtre-auto] recharge seulement les zones [data-resultats] de la page.
  document.querySelectorAll('form[data-filtre-auto]').forEach(function (formulaire) {
    var attente = null, courante = 0;
    function filtrer() {
      var params = new URLSearchParams(new FormData(formulaire));
      Array.from(params.keys()).forEach(function (k) { if (params.get(k) === '') { params.delete(k); } });
      var adresse = formulaire.getAttribute('action') + (params.toString() ? '?' + params.toString() : '');
      var numero = ++courante;
      fetch(adresse, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (numero !== courante) { return; }   // une frappe plus recente est en route
          var page = new DOMParser().parseFromString(html, 'text/html');
          var nouvelles = page.querySelectorAll('[data-resultats]');
          var zones = document.querySelectorAll('[data-resultats]');
          if (nouvelles.length !== zones.length) { window.location.href = adresse; return; }
          zones.forEach(function (zone, i) { zone.innerHTML = nouvelles[i].innerHTML; });
          window.history.replaceState(null, '', adresse);
        })
        .catch(function () { window.location.href = adresse; });
    }
    formulaire.addEventListener('input', function (e) {
      window.clearTimeout(attente);
      attente = window.setTimeout(filtrer, e.target.type === 'search' || e.target.type === 'text' ? 300 : 0);
    });
    formulaire.addEventListener('submit', function (e) { e.preventDefault(); window.clearTimeout(attente); filtrer(); });
  });

  // --- Bouton qui demande confirmation (un formulaire a plusieurs boutons) ---
  document.querySelectorAll('button[data-confirmer-clic]').forEach(function (bouton) {
    bouton.addEventListener('click', function (e) {
      if (!window.confirm(bouton.dataset.confirmerClic)) { e.preventDefault(); }
    });
  });

  // --- Controle : total de ce qui est coche et ecart avec l'argent depose ---
  var controle = document.querySelector('form[data-controle]');
  if (controle) {
    var cases = controle.querySelectorAll('input[name="ids[]"]');
    var zoneTotalControle = controle.querySelector('[data-controle-total]');
    var depose = controle.querySelector('[data-controle-depose]');
    var zoneEcart = controle.querySelector('[data-controle-ecart]');
    var symbole = controle.dataset.symbole || '';
    var ecrire = function (centimes) {
      var n = Math.abs(centimes) / 100;
      return n.toLocaleString('fr-FR', { minimumFractionDigits: Number.isInteger(n) ? 0 : 2, maximumFractionDigits: 2 }).replace(/ | /g, ' ') + ' ' + symbole;
    };
    var recompter = function () {
      var total = 0;
      cases.forEach(function (c) { if (c.checked) { total += parseInt(c.dataset.montant, 10) || 0; } });
      zoneTotalControle.textContent = ecrire(total);
      var saisie = depose.value.replace(/\s/g, '').replace(',', '.');
      if (saisie === '' || isNaN(parseFloat(saisie))) { zoneEcart.hidden = true; return; }
      var ecart = Math.round(parseFloat(saisie) * 100) - total;
      var libelle = zoneEcart.querySelector('[data-libelle]');
      libelle.textContent = ecart < 0 ? libelle.dataset.manquant : (ecart > 0 ? libelle.dataset.excedent : libelle.dataset.juste);
      zoneEcart.querySelector('strong').textContent = ecrire(ecart);
      zoneEcart.classList.toggle('alerte', ecart !== 0);
      zoneEcart.hidden = false;
    };
    controle.addEventListener('change', recompter);
    depose.addEventListener('input', recompter);
    var tout = controle.querySelector('[data-controle-tout]');
    if (tout) { tout.addEventListener('change', function () { cases.forEach(function (c) { c.checked = tout.checked; }); recompter(); }); }
    recompter();
  }

  // --- Connexion : mot de passe ou PIN, sans recharger la page ---
  var connexion = document.querySelector('[data-connexion]');
  if (connexion) {
    connexion.querySelectorAll('[data-basculer]').forEach(function (lien) {
      lien.addEventListener('click', function (e) {
        e.preventDefault();
        var saisi = '';
        connexion.querySelectorAll('[data-identifiant]').forEach(function (c) { if (!c.closest('form').hidden) { saisi = c.value; } });
        connexion.querySelectorAll('[data-zone]').forEach(function (zone) {
          zone.hidden = zone.dataset.zone !== lien.dataset.basculer;
          zone.querySelector('[data-identifiant]').value = saisi;
        });
        var visible = connexion.querySelector('[data-zone]:not([hidden])');
        (saisi === '' ? visible.querySelector('[data-identifiant]') : visible.querySelector('input[type=password]')).focus();
      });
    });
  }

  // --- Empreinte ou visage (cle de l'appareil, norme WebAuthn) ---
  function versOctets(texte) {
    var b = atob(texte.replace(/-/g, '+').replace(/_/g, '/'));
    var octets = new Uint8Array(b.length);
    for (var i = 0; i < b.length; i++) { octets[i] = b.charCodeAt(i); }
    return octets;
  }
  function versTexte(tampon) {
    var octets = new Uint8Array(tampon), s = '';
    for (var i = 0; i < octets.length; i++) { s += String.fromCharCode(octets[i]); }
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function envoyer(adresse, jeton, champs) {
    var corps = new URLSearchParams(champs);
    corps.set('_jeton', jeton);
    return fetch(adresse, { method: 'POST', credentials: 'same-origin', body: corps }).then(function (r) { return r.json(); });
  }
  var clesGerees = window.PublicKeyCredential && navigator.credentials && window.isSecureContext;

  // Connexion
  var boutonCle = connexion ? connexion.querySelector('[data-cle-connexion]') : null;
  if (boutonCle && clesGerees) {
    boutonCle.hidden = false;
    var erreurCle = connexion.querySelector('[data-cle-erreur]');
    boutonCle.addEventListener('click', function () {
      erreurCle.hidden = true;
      var champ = connexion.querySelector('[data-zone]:not([hidden]) [data-identifiant]');
      if (champ.value.trim() === '') { champ.focus(); champ.reportValidity(); return; }
      envoyer(connexion.dataset.defi, connexion.dataset.jeton, { identifiant: champ.value }).then(function (defi) {
        if (!defi.cles.length) { throw new Error('aucune cle'); }
        return navigator.credentials.get({ publicKey: {
          challenge: versOctets(defi.challenge), rpId: defi.rpId, userVerification: 'required', timeout: 60000,
          allowCredentials: defi.cles.map(function (id) { return { type: 'public-key', id: versOctets(id) }; })
        } });
      }).then(function (cle) {
        return envoyer(connexion.dataset.envoi, connexion.dataset.jeton, {
          id: versTexte(cle.rawId), clientData: versTexte(cle.response.clientDataJSON),
          donnees: versTexte(cle.response.authenticatorData), signature: versTexte(cle.response.signature)
        });
      }).then(function (r) {
        if (r.ok) { window.location.href = r.suite; return; }
        erreurCle.textContent = r.message; erreurCle.hidden = false;
      }).catch(function () {
        erreurCle.textContent = boutonCle.dataset.echec || 'Connexion par empreinte impossible sur cet appareil pour ce compte.';
        erreurCle.hidden = false;
      });
    });
  }

  // Ajout d'un appareil depuis « Mes identifiants »
  var zoneCle = document.querySelector('[data-cle-appareil]');
  if (zoneCle && clesGerees) {
    zoneCle.querySelector('[data-cle-zone]').hidden = false;
    zoneCle.querySelector('[data-cle-indisponible]').hidden = true;
    var erreurAjout = zoneCle.querySelector('[data-cle-erreur]');
    zoneCle.querySelector('[data-cle-ajouter]').addEventListener('click', function () {
      erreurAjout.hidden = true;
      envoyer(zoneCle.dataset.defi, zoneCle.dataset.jeton, {}).then(function (d) {
        return navigator.credentials.create({ publicKey: {
          challenge: versOctets(d.challenge), rp: { name: d.rpNom, id: d.rpId },
          user: { id: versOctets(d.utilisateur.id), name: d.utilisateur.nom, displayName: d.utilisateur.affichage },
          pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
          authenticatorSelection: { authenticatorAttachment: 'platform', userVerification: 'required', residentKey: 'preferred' },
          excludeCredentials: d.exclure.map(function (id) { return { type: 'public-key', id: versOctets(id) }; }),
          attestation: 'none', timeout: 60000
        } });
      }).then(function (cle) {
        return envoyer(zoneCle.dataset.envoi, zoneCle.dataset.jeton, {
          clientData: versTexte(cle.response.clientDataJSON), attestation: versTexte(cle.response.attestationObject),
          nom: zoneCle.querySelector('[data-cle-nom]').value
        });
      }).then(function (r) {
        if (r.ok) { window.location.href = r.suite; return; }
        erreurAjout.textContent = r.message; erreurAjout.hidden = false;
      }).catch(function () {
        erreurAjout.textContent = zoneCle.querySelector('[data-cle-indisponible]').textContent;
        erreurAjout.hidden = false;
      });
    });
  }

  // Fenetres (identifiants a remettre au client) et impression
  document.querySelectorAll('dialog[data-ouvrir]').forEach(function (d) {
    if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
  });
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-fermer-fenetre]');
    if (b) { var d = b.closest('dialog'); if (d) { d.close ? d.close() : d.removeAttribute('open'); } }
    if (ev.target.closest('[data-imprimer]')) { window.print(); }
  });

  // Saisie en masse : un choix fait sur une ligne se recopie sur les lignes suivantes encore vides, et on peut ajouter des lignes
  document.querySelectorAll('table[data-lignes]').forEach(function (table) {
    table.addEventListener('change', function (ev) {
      var s = ev.target.closest('select[data-recopier]');
      if (!s) { return; }
      var colonne = s.name.replace(/^lignes\[\d+\]/, '');
      var apres = false;
      table.querySelectorAll('select[data-recopier]').forEach(function (autre) {
        if (autre === s) { apres = true; return; }
        if (apres && autre.name.replace(/^lignes\[\d+\]/, '') === colonne && autre.value === '0') { autre.value = s.value; }
      });
    });
    var bouton = table.closest('form').querySelector('[data-ajouter-lignes]');
    if (bouton) {
      bouton.addEventListener('click', function () {
        var corps = table.tBodies[0];
        for (var k = 0; k < 10; k++) {
          var n = corps.rows.length;
          var ligne = corps.rows[n - 1].cloneNode(true);
          ligne.cells[0].textContent = n + 1;
          ligne.querySelectorAll('input,select').forEach(function (champ) {
            champ.name = champ.name.replace(/^lignes\[\d+\]/, 'lignes[' + n + ']');
            if (champ.tagName === 'INPUT') { champ.value = ''; }
          });
          ligne.querySelectorAll('.erreur-champ').forEach(function (e) { e.remove(); });
          corps.appendChild(ligne);
        }
      });
    }
  });

  // Application installable (PWA) : le service worker garde les fichiers statiques et affiche
  // une page claire quand il n'y a pas de reseau. Il est a la racine du dossier public.
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    var script = document.querySelector('script[src*="assets/js/application.js"]');
    if (script) {
      var racine = script.src.split('assets/js/application.js')[0];
      navigator.serviceWorker.register(racine + 'sw.js', { scope: racine }).catch(function () {});
    }
  }
})();
