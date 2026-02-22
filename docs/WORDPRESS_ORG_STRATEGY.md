# Stratégie de Publication sur WordPress.org Plugin Directory

## 1. Processus de soumission

### Étape 1 : Préparer le plugin

- [x] `README.txt` au format WordPress.org (validé via https://wordpress.org/plugins/developers/readme-validator/)
- [x] `README.md` pour GitHub
- [x] Tests unitaires et CI
- [x] Licence GPL-2.0+ (fichier + header du plugin)
- [ ] Vérifier que le slug `wp-houla` est disponible sur wordpress.org
- [ ] Créer les assets graphiques (bannière, icône, screenshots — voir `.wordpress-org/ASSETS_GUIDE.md`)
- [ ] Valider le plugin avec Plugin Check (PCP) : https://wordpress.org/plugins/plugin-check/

### Étape 2 : Soumettre pour review

1. Aller sur https://wordpress.org/plugins/developers/add/
2. Se connecter avec un compte WordPress.org (en créer un si nécessaire)
3. Uploader le ZIP du plugin
4. Remplir le formulaire de soumission avec les informations du plugin
5. Attendre l'email de review (délai : **1 à 10 jours ouvrés**, souvent 3-5)

### Étape 3 : Répondre à la review

Les reviewers vérifient :
- Conformité GPL
- Pas de code obfusqué
- Pas de liens affiliés cachés
- Pas de tracking sans consentement
- Appels vers des services externes déclarés
- Respect des guidelines : https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

**Points d'attention pour WP-Houla :**
- ✅ OAuth 2.0 : l'utilisateur connecte explicitement son compte → consentement clair
- ✅ API externe (api.hou.la) : déclaré dans la description du plugin
- ⚠️ Ajouter une mention dans la description que le plugin communique avec un service externe (hou.la)
- ⚠️ Vérifier que toutes les chaînes sont traduisibles (textdomain `wp-houla`)

### Étape 4 : Première publication SVN

Après approbation, WordPress.org fournit un accès SVN :

```bash
svn co https://plugins.svn.wordpress.org/wp-houla/
cd wp-houla/

# Structure SVN :
# trunk/        → version courante du plugin
# tags/1.0.0/   → version releasée
# assets/       → bannière, icône, screenshots

# Copier les fichiers du plugin dans trunk/
cp -r /path/to/wp-houla/* trunk/

# Copier les assets
cp /path/to/.wordpress-org/* assets/

# Commit
svn add trunk/* assets/*
svn ci -m "Initial release 1.0.0"

# Créer le tag de la version
svn cp trunk tags/1.0.0
svn ci -m "Tag version 1.0.0"
```

### Automatisation SVN via GitHub Actions

Ajouter un workflow GitHub Actions pour pousser automatiquement vers SVN à chaque release :

```yaml
# .github/workflows/wp-deploy.yml
name: Deploy to WordPress.org
on:
  release:
    types: [published]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: WordPress Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SLUG: wp-houla
          BUILD_DIR: master/wp-houla
          ASSETS_DIR: master/wp-houla/.wordpress-org
```

Secrets à configurer dans GitHub : `SVN_USERNAME` et `SVN_PASSWORD` (identifiants WordPress.org).

---

## 2. Optimisation SEO du listing

### Titre du plugin
**"WP-Houla — Short Links, QR Codes & Social Commerce for WordPress"**

Le titre inclut les mots-clés principaux que les utilisateurs recherchent.

### Tags (max 5 visibles, jusqu'à 12 acceptés)
```
short links, qr code, url shortener, woocommerce, social commerce, link in bio, link shortener, analytics, marketing
```

Optimisés pour les recherches fréquentes :
- "url shortener" / "short links" / "link shortener" → cœur de métier
- "qr code" → fonctionnalité très recherchée
- "woocommerce" → visibilité dans l'écosystème WC
- "social commerce" / "link in bio" → différenciant

### Description courte (< 150 caractères)
> Turn every WordPress post into a short link with QR code and sync your WooCommerce catalog to your Hou.la bio page for social selling.

### Description longue
Structurée avec des headings Markdown, des listes à puces, et des use-cases clairs. Inclut les mots-clés naturellement.

### Bonnes pratiques SEO WordPress.org
1. **Short description** : Mots-clés en premier, valeur ajoutée claire
2. **Tags** : Utiliser les termes que les utilisateurs tapent réellement
3. **Active installs** : Plus d'installations → meilleur classement (cercle vertueux)
4. **Rating** : Encourager les avis 5 étoiles (via notice admin discrète après 7 jours)
5. **Support forum** : Répondre rapidement (< 24h) améliore le "support resolution rate"
6. **Compatibility** : Mettre à jour "Tested up to" à chaque release WordPress majeure
7. **Changelog** : Mises à jour fréquentes (au moins trimestrielles) → signe de plugin actif

---

## 3. Stratégie de lancement

### Phase 1 : Pré-lancement (avant soumission)

- [ ] Créer un compte WordPress.org si nécessaire
- [ ] Installer Plugin Check (PCP) en local et corriger tous les avertissements
- [ ] Valider le README.txt avec le validateur officiel
- [ ] Créer les assets graphiques (bannière + icône + 7 screenshots)
- [ ] Rédiger un article de blog sur hou.la annonçant le plugin
- [ ] Préparer un post Twitter/X et LinkedIn

### Phase 2 : Soumission et approbation

- [ ] Soumettre le plugin sur wordpress.org
- [ ] Répondre aux éventuels retours des reviewers sous 24h
- [ ] Une fois approuvé : premier commit SVN (trunk + tag 1.0.0 + assets)
- [ ] Configurer le workflow `wp-deploy.yml` avec les secrets SVN
- [ ] Vérifier que la page du plugin s'affiche correctement

### Phase 3 : Lancement (première semaine)

- [ ] Publier l'article de blog
- [ ] Partager sur les réseaux sociaux (Twitter, LinkedIn, Reddit r/WordPress)
- [ ] Annoncer dans les groupes WooCommerce (Slack, Facebook)
- [ ] Envoyer une newsletter aux utilisateurs Hou.la existants
- [ ] Soumettre sur Product Hunt
- [ ] Ajouter un lien vers le plugin dans le footer de hou.la
- [ ] Ajouter une bannière/lien dans l'interface Hou.la pour les utilisateurs WordPress

### Phase 4 : Croissance continue

- **Avis** :
  - Ajouter une notice admin discrète demandant un avis après 7 jours d'utilisation (avec "Ne plus afficher")
  - Répondre à chaque avis (positif ou négatif) sur wordpress.org
  - Ne jamais demander d'avis de manière agressive (interdit par les guidelines)

- **Support** :
  - Surveiller le forum de support wordpress.org quotidiennement
  - Temps de réponse < 24h (impacte le classement)
  - Résoudre les tickets ouverts (le taux de résolution est affiché)

- **Mises à jour** :
  - Mettre à jour "Tested up to" à chaque release WordPress majeure
  - Au minimum une release par trimestre (signe de plugin vivant)
  - Changelog détaillé à chaque version

- **Contenu SEO** :
  - Créer une page dédiée sur hou.la/fonctionnalites/wordpress
  - Articles de blog : tutoriels, comparatifs, guides
  - Backlinks depuis des annuaires de plugins WordPress

---

## 4. Checklist de conformité WordPress.org

### Guidelines obligatoires

| # | Règle | Statut |
|---|-------|--------|
| 1 | Plugin doit être GPL-2.0+ ou compatible | ✅ |
| 2 | Pas de code obfusqué | ✅ |
| 3 | L'auteur maintient un support actif | ⬜ À faire |
| 4 | Pas de liens/scripts tiers sans consentement | ✅ (OAuth explicite) |
| 5 | Pas de tracking sans consentement | ✅ |
| 6 | Headers du plugin corrects | ✅ |
| 7 | Slug unique | ⬜ À vérifier |
| 8 | Fichier readme.txt conforme | ✅ |
| 9 | Pas d'appels AJAX vers domaines externes sans notification | ⚠️ Ajouter mention |
| 10 | Pas de publicités dans l'admin | ✅ |
| 11 | Pas de fausses urgences ou dark patterns | ✅ |
| 12 | Nonces et vérifications de capacités | ✅ (check_admin_referer, current_user_can) |

### Point critique : Déclaration de service externe

WordPress.org exige que tout appel à un service externe soit clairement mentionné. Ajouter dans la section Description du README.txt :

```
= External Service =

This plugin connects to the [Hou.la](https://hou.la) API (`api.hou.la`) to:
* Generate short links and QR codes
* Synchronize WooCommerce products
* Receive order webhooks

Data sent: post URLs, post titles, product details (name, price, images, stock).
Data received: short link URLs, QR code URLs, order data.

No data is sent until the user explicitly connects their Hou.la account via OAuth.

* [Hou.la Terms of Service](https://hou.la/conditions-generales-utilisation)
* [Hou.la Privacy Policy](https://hou.la/politique-de-confidentialite)
```

---

## 5. Métriques de succès

| Métrique | Objectif M+1 | Objectif M+3 | Objectif M+6 |
|----------|-------------|-------------|-------------|
| Active installs | 50+ | 200+ | 1 000+ |
| Rating | 5.0 (3+ avis) | 4.8+ (10+ avis) | 4.5+ (25+ avis) |
| Support resolution | 100% | > 90% | > 85% |
| Download count | 200+ | 1 000+ | 5 000+ |
| Tested up to | 6.7 | 6.8 | 6.9 |

---

## 6. Outils utiles

| Outil | URL | Usage |
|-------|-----|-------|
| README Validator | https://wordpress.org/plugins/developers/readme-validator/ | Valider README.txt |
| Plugin Check (PCP) | https://wordpress.org/plugins/plugin-check/ | Audit de conformité |
| Plugin Submission | https://wordpress.org/plugins/developers/add/ | Soumettre le plugin |
| SVN Deploy Action | https://github.com/10up/action-wordpress-plugin-deploy | CI/CD vers SVN |
| Plugin Guidelines | https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ | Règles à respecter |
| Plugin Assets | https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/ | Guide des assets |
| Plugin Headers | https://developer.wordpress.org/plugins/plugin-basics/header-requirements/ | Headers requis |
