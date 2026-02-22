# WP-Houla - Liens courts, QR codes et commerce social pour WordPress

Connectez votre site WordPress a [Hou.la](https://hou.la) pour generer automatiquement des liens courts et des QR codes sur tous vos contenus, et synchroniser votre catalogue WooCommerce avec votre page bio pour vendre directement depuis les reseaux sociaux.

---

## Sommaire

- [Presentation](#presentation)
- [Fonctionnalites](#fonctionnalites)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
  - [Liens courts automatiques](#liens-courts-automatiques)
  - [QR codes](#qr-codes)
  - [Shortcode](#shortcode-wphoula)
  - [Synchronisation WooCommerce](#synchronisation-woocommerce)
  - [Reception de commandes](#reception-de-commandes)
- [Page de reglages](#page-de-reglages)
- [Metabox dans l'editeur](#metabox-dans-lediteur)
- [Desinstallation](#desinstallation)
- [FAQ](#faq)
- [Hooks et filtres](#hooks-et-filtres)
- [Architecture technique](#architecture-technique)
- [Developpement](#developpement)
- [Support](#support)
- [Licence](#licence)

---

## Presentation

**WP-Houla** est une extension WordPress qui integre votre site avec la plateforme [Hou.la](https://hou.la), un outil marketing de generation de liens courts, QR codes et pages bio avec commerce integre.

L'extension couvre deux cas d'usage principaux :

1. **Liens courts et QR codes** : chaque article, page ou contenu publie sur votre site obtient automatiquement un lien court Hou.la et un QR code associe. Le raccourcisseur WordPress natif (`get_shortlink()`) est remplace par le lien Hou.la. Vous accedez aux statistiques de clics directement dans l'editeur.

2. **Commerce social via WooCommerce** : vos produits WooCommerce sont synchronises en temps reel avec votre page bio Hou.la. Les commandes passees par vos clients via Hou.la Pay (Stripe) sont automatiquement creees dans WooCommerce avec gestion des stocks et remboursements.

---

## Fonctionnalites

### Liens courts

- Generation automatique d'un lien court Hou.la a la publication de tout type de contenu public (article, page, CPT)
- Remplacement du raccourcisseur WordPress natif : le bouton "Obtenir le lien court" retourne le lien Hou.la
- Copie en un clic depuis la metabox de l'editeur
- Regeneration manuelle si besoin (changement d'URL, etc.)

### QR codes

- QR code genere automatiquement avec chaque lien court
- Apercu du QR code dans la metabox de l'editeur
- Telechargement direct du QR code en image
- Insertion dans le contenu via le shortcode `[wphoula qrcode=1]`

### Statistiques

- Nombre total de clics
- Clics du jour
- Nombre de scans QR code
- Affichage dans la metabox laterale de chaque article/page

### Shortcode [wphoula]

- `[wphoula]` : affiche le lien court de la publication courante
- `[wphoula text="Cliquez ici"]` : personnalise le texte du lien
- `[wphoula qrcode=1]` : affiche l'image du QR code
- `[wphoula post_id="42"]` : affiche le lien d'une publication specifique

### Synchronisation WooCommerce

- Synchronisation automatique a la creation, modification et suppression de produits
- Synchronisation des stocks en temps reel (produits simples et variations)
- Synchronisation par lot ("Batch sync") de tout le catalogue en un clic
- Donnees synchronisees : nom, description, prix (normal, solde), images, galerie, categories, tags, SKU, dimensions, poids, variations, attributs

### Reception de commandes

- Endpoint webhook securise (`POST /wp-json/wp-houla/v1/webhook`)
- Verification HMAC-SHA256 de chaque requete entrante
- Creation automatique de commandes WooCommerce avec statut "En cours de traitement"
- Decrementation automatique des stocks
- Gestion des remboursements (re-stockage automatique)
- Detection des doublons

### Securite

- Authentification OAuth 2.0 avec PKCE (Proof Key for Code Exchange)
- Chiffrement des tokens d'acces au repos (AES-256-CBC)
- Protection CSRF via parametre `state`
- Renouvellement automatique du token avant expiration
- Verification HMAC-SHA256 sur tous les webhooks entrants

---

## Prerequis

| Composant | Version minimale |
|-----------|-----------------|
| WordPress | 5.8 |
| PHP | 7.4 |
| WooCommerce | 7.0 |
| Extension OpenSSL PHP | requise |

Un compte Hou.la (gratuit ou Pro) est necessaire. Creez un compte sur [hou.la](https://hou.la).

---

## Installation

### Depuis un fichier ZIP

1. Telechargez la derniere release depuis [GitHub Releases](https://github.com/MikhaelGerbet/wp-houla/releases)
2. Dans l'administration WordPress, allez dans **Extensions > Ajouter**
3. Cliquez sur **Telecharger une extension**
4. Selectionnez le fichier `wp-houla-x.x.x.zip`
5. Cliquez sur **Installer maintenant** puis **Activer**

### Depuis les fichiers sources

1. Clonez le depot :
   ```bash
   git clone https://github.com/MikhaelGerbet/wp-houla.git
   ```
2. Copiez le dossier `master/wp-houla/` dans `wp-content/plugins/`
3. Activez l'extension depuis le menu **Extensions**

### Depuis le repertoire WordPress.org (a venir)

1. Dans **Extensions > Ajouter**, recherchez "WP-Houla"
2. Cliquez sur **Installer maintenant** puis **Activer**

---

## Configuration

### 1. Connexion a Hou.la

1. Allez dans **WooCommerce > Hou.la**
2. Cliquez sur **Connecter a Hou.la**
3. Autorisez l'application dans la fenetre Hou.la
4. Vous etes redirige automatiquement avec le statut "Connecte"

Le nom du workspace et l'email du compte apparaissent dans les reglages.

### 2. Parametres de synchronisation

Dans l'onglet **Synchronisation** :

- **Synchronisation automatique** (active par defaut) : les produits WooCommerce sont pousses vers Hou.la a chaque modification.
- **Sync a la publication** (active par defaut) : les nouveaux produits publies sont synchronises immediatement.
- **Synchronisation par lot** : envoi de l'ensemble du catalogue en un clic (utile apres installation initiale ou import).

### 3. Commandes

Les commandes sont recues via webhook. Aucune configuration manuelle n'est requise. Le webhook est enregistre automatiquement a la connexion OAuth.

### 4. Debug

Activez le mode debug dans l'onglet **Debug** pour journaliser les appels API dans les logs WordPress (`WP_DEBUG_LOG`).

---

## Utilisation

### Liens courts automatiques

Des que l'extension est connectee :

- **Publication** : a la publication d'un contenu, un lien court est genere automatiquement.
- **Contenus existants** : le lien est genere a la premiere consultation ou via le bouton "Generer un lien court" dans la metabox.
- **Raccourcisseur WordPress** : `get_shortlink()` retourne desormais le lien Hou.la.

Les liens sont stockes en meta du post et ne generent pas d'appel API supplementaire apres creation.

### QR codes

Chaque lien court genere un QR code visible dans la metabox de l'editeur :

- **Apercu** : image du QR code dans la colonne laterale
- **Telechargement** : bouton "Telecharger QR"
- **Insertion** : shortcode `[wphoula qrcode=1]` dans le contenu

### Shortcode [wphoula]

| Exemple | Resultat |
|---------|----------|
| `[wphoula]` | Lien court cliquable de la publication courante |
| `[wphoula text="Voir"]` | Lien avec texte personnalise |
| `[wphoula qrcode=1]` | Image QR code (200x200) |
| `[wphoula post_id="42"]` | Lien court d'une publication specifique |

**Parametres :**

| Parametre | Description | Defaut |
|-----------|-------------|--------|
| `text` | Texte du lien | URL du lien court |
| `title` | Attribut title | Titre de la publication |
| `post_id` | ID de la publication | Publication courante |
| `qrcode` | Afficher le QR code | `false` |
| `before` | HTML avant le lien | vide |
| `after` | HTML apres le lien | vide |

### Synchronisation WooCommerce

Trois modes :

1. **Automatique** : chaque creation/modification/suppression pousse les changements vers Hou.la.
2. **Manuelle** : boutons "Synchroniser" et "Retirer" dans la metabox produit.
3. **Par lot** : synchronisation complete depuis les reglages (produits traites par pages de 50).

**Donnees synchronisees :**

| Champ | Description |
|-------|-------------|
| Nom | Nom du produit |
| Description | Description courte (ou longue) |
| URL | Permalien |
| Prix | Prix courant, normal, solde |
| Devise | Devise du site |
| Images | Image principale + galerie |
| Categories | Categories produit |
| Tags | Etiquettes |
| SKU | Reference |
| Poids | Poids du produit |
| Dimensions | Longueur, largeur, hauteur |
| Stock | Quantite + statut |
| Variations | Prix, image, SKU, stock, attributs |
| Attributs | Attributs visibles |

### Reception de commandes

Quand un client achete via votre page bio Hou.la :

1. Stripe traite le paiement via Hou.la Pay
2. Hou.la envoie un webhook a votre site
3. L'extension verifie la signature HMAC-SHA256
4. Une commande WooCommerce est creee (statut "processing", methode "Hou.la Pay (Stripe)")
5. Les stocks sont decrementes

En cas de remboursement, un webhook `order.refunded` declenche le remboursement WooCommerce avec re-stockage.

---

## Page de reglages

Accessible via **WooCommerce > Hou.la** :

| Onglet | Contenu |
|--------|---------|
| Connexion | Statut, workspace, email, bouton connexion/deconnexion |
| Synchronisation | Options auto-sync, derniere synchro, compteur, bouton batch |
| Commandes | Compteur commandes, derniere commande, URL webhook |
| Debug | Activation des journaux |

---

## Metabox dans l'editeur

### Articles, pages et CPT

Metabox "Hou.la" dans la colonne laterale :
- Lien court + bouton copie
- Apercu QR code + telechargement
- Statistiques (clics total, clics jour, scans QR)
- Bouton generer/regenerer

### Produits WooCommerce

Metabox avec en plus :
- Statut de synchronisation et date
- Statistiques commerce (vues, clics, ventes, CA)
- Boutons synchroniser/retirer
- Lien court + QR code

---

## Desinstallation

La suppression via le menu Extensions nettoie :

- Options (`wphoula_options`, `wphoula_authorized`)
- Meta de posts (liens, IDs produit, statuts sync)
- Meta de commandes (IDs Hou.la, IDs transaction)
- Transients

Les liens courts Hou.la restent actifs. Gerez-les depuis votre tableau de bord Hou.la.

---

## FAQ

### L'extension fonctionne-t-elle sans WooCommerce ?

WooCommerce est requis pour l'activation. Les liens courts et QR codes fonctionnent sur tous les types de contenu independamment du commerce.

### Faut-il un abonnement payant ?

Non. Le plan gratuit permet d'utiliser toutes les fonctionnalites. Seule la commission sur les ventes differe : 8% (gratuit) vs 3% (Pro). Les liens courts et QR codes sont gratuits et illimites.

### Comment sont geres les paiements ?

Les paiements sont traites par Hou.la via Stripe Connect. L'extension WordPress ne gere aucune donnee de paiement. Stripe assure la conformite PCI DSS.

### Les liens restent-ils actifs apres desinstallation ?

Oui. Les liens sur Hou.la persistent. Seules les meta WordPress locales sont supprimees.

### Comment re-generer un lien court ?

Cliquez sur "Regenerer" dans la metabox Hou.la. Un nouveau lien est cree ; l'ancien reste actif.

### Les brouillons ont-ils un lien court ?

Non. Les liens sont generes uniquement pour les contenus publies, planifies ou prives.

### La synchro par lot peut-elle etre lancee plusieurs fois ?

Un verrou de 10 minutes empeche les synchronisations simultanees. Si une synchro est en cours, attendez qu'elle se termine ou que le verrou expire.

### Quels types de contenus supportent les liens courts ?

Tous les types publics : articles, pages, et tout CPT avec `public => true`. Personnalisez avec le filtre `wphoula_allowed_post_types`.

### Comment personnaliser les types de contenus ?

```php
add_filter( 'wphoula_allowed_post_types', function( $types ) {
    // Retirer les pages
    $types = array_diff( $types, array( 'page' ) );
    return $types;
} );
```

### Les webhooks fonctionnent-ils avec Cloudflare / Nginx ?

Oui, tant que le header `X-Houla-Signature` est transmis. La plupart des CDN et reverse proxies le font par defaut.

---

## Hooks et filtres

### Filtres

| Filtre | Description | Parametres |
|--------|-------------|------------|
| `wphoula_allowed_post_types` | Types de contenus pour les liens courts | `array $types` |
| `wphoula_default_options` | Options par defaut du plugin | `array $defaults` |

---

## Architecture technique

```
wp-houla/
  wp-houla.php                      Fichier principal (constantes, bootstrap)
  uninstall.php                     Nettoyage a la desinstallation
  includes/
    class-wp-houla.php              Classe centrale (deps, hooks)
    class-wp-houla-loader.php       File d'attente des hooks
    class-wp-houla-i18n.php         Chargement du text domain
    class-wp-houla-options.php      CRUD options + chiffrement AES-256-CBC
    class-wp-houla-activator.php    Activation (check PHP, secret webhook)
    class-wp-houla-deactivator.php  Desactivation (nettoyage transients)
    class-wp-houla-auth.php         OAuth 2.0 + PKCE
    class-wp-houla-api.php          Client HTTP pour l'API Hou.la
    class-wp-houla-shortlink.php    Generation de liens courts
    class-wp-houla-post-metabox.php Metabox des posts (lien + QR + stats)
    class-wp-houla-sync.php         Synchronisation produits (WC -> Hou.la)
    class-wp-houla-orders.php       Creation commandes (Hou.la -> WC)
    class-wp-houla-webhook.php      Endpoint REST + verification HMAC
    class-wp-houla-metabox.php      Metabox produits (sync + stats)
  admin/
    class-wp-houla-admin.php        Menu admin, reglages, AJAX
    css/wp-houla-admin.css          Styles admin
    js/wp-houla-admin.js            JavaScript admin
    images/houla-icon.svg           Icone du plugin
    partials/
      settings-page.php             Template reglages (4 onglets)
      metabox-product.php           Template metabox produit
  languages/
    wp-houla.pot                    Template de traduction
```

### Meta de posts

| Cle | Scope | Description |
|-----|-------|-------------|
| `_wphoula_shortlink` | tout post | URL du lien court |
| `_wphoula_link_id` | tout post | ID du lien sur Hou.la |
| `_wphoula_qrcode` | tout post | URL de l'image QR code |
| `_wphoula_product_id` | produit | ID produit sur Hou.la |
| `_wphoula_synced` | produit | Flag de synchronisation |
| `_wphoula_sync_at` | produit | Date derniere synchro |
| `_houla_order_id` | commande | ID commande Hou.la |
| `_houla_transaction_id` | commande | ID transaction Stripe |

---

## Developpement

### Tests unitaires

```bash
cd master/wp-houla
composer install
vendor/bin/phpunit
```

### Tests d'integration

```bash
vendor/bin/phpunit --testsuite integration
```

### Standards de code

```bash
composer run phpcs
```

### Build du package

```bash
# Windows PowerShell
.\build.ps1
# Le ZIP est genere dans releases/
```

### Contribuer

1. Fork le depot
2. Creez une branche (`git checkout -b feature/ma-fonctionnalite`)
3. Commitez (`git commit -m 'feat: description'`)
4. Pushez (`git push origin feature/ma-fonctionnalite`)
5. Ouvrez une Pull Request

---

## Support

- Site web : [hou.la](https://hou.la)
- Signaler un bug : [GitHub Issues](https://github.com/MikhaelGerbet/wp-houla/issues)
- Email : contact@hou.la

---

## Licence

GPLv2 ou ulterieure. Voir [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
