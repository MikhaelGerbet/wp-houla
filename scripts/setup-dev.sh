#!/bin/bash
##
# First-time setup script for local WooCommerce dev environment.
# Run inside the WordPress container:
#   docker compose -f docker-compose.dev.yml exec wordpress bash /var/www/html/wp-content/plugins/wp-houla/scripts/setup-dev.sh
##

set -e

WP="/usr/local/bin/wp --allow-root --path=/var/www/html"

echo "=== Hou.la WooCommerce Dev Setup ==="

# ──────────────────────────────────────────────────────────────
# 1. Complete WordPress installation if needed
# ──────────────────────────────────────────────────────────────
if ! $WP core is-installed 2>/dev/null; then
  echo "→ Installing WordPress..."
  $WP core install \
    --url="http://localhost:8080" \
    --title="Hou.la Dev Shop" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=dev@hou.la \
    --skip-email
fi

# Get admin user ID (works regardless of username)
ADMIN_ID=$($WP user list --role=administrator --field=ID --format=csv 2>/dev/null | head -1)
if [ -z "$ADMIN_ID" ]; then
  ADMIN_ID=1
fi
echo "  Admin user ID: $ADMIN_ID"

# ──────────────────────────────────────────────────────────────
# 2. Install & activate WooCommerce
# ──────────────────────────────────────────────────────────────
if ! $WP plugin is-active woocommerce 2>/dev/null; then
  echo "→ Installing WooCommerce..."
  php -d memory_limit=512M /usr/local/bin/wp --allow-root --path=/var/www/html plugin install woocommerce --activate
fi

# ──────────────────────────────────────────────────────────────
# 3. Activate wp-houla plugin
# ──────────────────────────────────────────────────────────────
if ! $WP plugin is-active wp-houla 2>/dev/null; then
  echo "→ Activating wp-houla..."
  $WP plugin activate wp-houla
fi

# ──────────────────────────────────────────────────────────────
# 3b. Configure wp-houla for local development
# ──────────────────────────────────────────────────────────────
echo "→ Configuring wp-houla for dev..."

# Read existing options (activation may have created webhook_secret)
EXISTING_SECRET=$($WP eval '
  $opts = get_option("wphoula-options", array());
  echo isset($opts["webhook_secret"]) ? $opts["webhook_secret"] : "";
' 2>/dev/null || echo "")

# Generate webhook secret if empty
if [ -z "$EXISTING_SECRET" ]; then
  EXISTING_SECRET=$(openssl rand -hex 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-f0-9' | head -c 64)
fi

# Set all dev options at once via wp eval (handles PHP serialization properly)
$WP eval '
  $opts = get_option("wphoula-options", array());

  // Dev mode — point to local API via Docker internal network
  $opts["api_url"]      = "http://host.docker.internal:53001";
  $opts["debug"]        = true;

  // Sync defaults for development
  $opts["auto_sync"]          = true;
  $opts["sync_on_publish"]    = true;
  $opts["sync_categories"]    = array();
  $opts["sync_tracking"]      = true;
  $opts["allowed_post_types"] = array("post", "page", "product");

  // Order status mapping (WooCommerce → Hou.la)
  $opts["order_status_map"] = array(
    "wc-on-hold"    => "pending",
    "wc-processing" => "processing",
    "wc-completed"  => "delivered",
    "wc-cancelled"  => "cancelled",
    "wc-refunded"   => "refunded",
  );

  // Preserve existing webhook secret or use new one
  if (empty($opts["webhook_secret"])) {
    $opts["webhook_secret"] = "'"$EXISTING_SECRET"'";
  }

  // Price adjustment off by default
  $opts["price_adjustment_type"]  = "none";
  $opts["price_adjustment_value"] = 0;

  update_option("wphoula-options", $opts);
  echo "  ✓ wp-houla options configured";
  echo "\n  API URL: " . $opts["api_url"];
  echo "\n  Debug: enabled";
  echo "\n  Auto-sync: enabled";
' 2>/dev/null || echo "  ⚠ wp-houla options update failed (try manual config)"

# ──────────────────────────────────────────────────────────────
# 4. Configure WooCommerce basics
# ──────────────────────────────────────────────────────────────
echo "→ Configuring WooCommerce..."
# --- Store identity ---
$WP option update woocommerce_store_address "1 Rue du Dev" 2>/dev/null || true
$WP option update woocommerce_store_address_2 "" 2>/dev/null || true
$WP option update woocommerce_store_city "Paris" 2>/dev/null || true
$WP option update woocommerce_store_postcode "75001" 2>/dev/null || true
$WP option update woocommerce_default_country "FR" 2>/dev/null || true
$WP option update blogname "Hou.la Dev Shop" 2>/dev/null || true
$WP option update blogdescription "Boutique de test locale" 2>/dev/null || true

# --- Currency ---
$WP option update woocommerce_currency "EUR" 2>/dev/null || true
$WP option update woocommerce_currency_pos "right_space" 2>/dev/null || true
$WP option update woocommerce_price_thousand_sep " " 2>/dev/null || true
$WP option update woocommerce_price_decimal_sep "," 2>/dev/null || true
$WP option update woocommerce_price_num_decimals "2" 2>/dev/null || true

# --- Taxes (disabled for simplicity) ---
$WP option update woocommerce_calc_taxes "no" 2>/dev/null || true

# --- Weight & dimensions (metric) ---
$WP option update woocommerce_weight_unit "kg" 2>/dev/null || true
$WP option update woocommerce_dimension_unit "cm" 2>/dev/null || true

# --- Products display ---
$WP option update woocommerce_shop_page_display "" 2>/dev/null || true
$WP option update woocommerce_default_catalog_orderby "date" 2>/dev/null || true
$WP option update woocommerce_catalog_rows "4" 2>/dev/null || true
$WP option update woocommerce_catalog_columns "4" 2>/dev/null || true

# --- Inventory ---
$WP option update woocommerce_manage_stock "yes" 2>/dev/null || true
$WP option update woocommerce_notify_low_stock_amount "5" 2>/dev/null || true
$WP option update woocommerce_notify_no_stock_amount "0" 2>/dev/null || true
$WP option update woocommerce_hide_out_of_stock_items "no" 2>/dev/null || true
$WP option update woocommerce_stock_format "" 2>/dev/null || true

# --- Checkout & accounts ---
$WP option update woocommerce_enable_guest_checkout "yes" 2>/dev/null || true
$WP option update woocommerce_enable_checkout_login_reminder "yes" 2>/dev/null || true
$WP option update woocommerce_enable_signup_and_login_from_checkout "yes" 2>/dev/null || true
$WP option update woocommerce_enable_myaccount_registration "yes" 2>/dev/null || true
$WP option update woocommerce_checkout_privacy_policy_text "" 2>/dev/null || true

# --- Emails (use default WordPress mailer) ---
$WP option update woocommerce_email_from_name "Hou.la Dev Shop" 2>/dev/null || true
$WP option update woocommerce_email_from_address "dev@hou.la" 2>/dev/null || true

# --- REST API (needed for wp-houla plugin) ---
$WP option update woocommerce_api_enabled "yes" 2>/dev/null || true

# --- Shipping defaults ---
$WP option update woocommerce_ship_to_countries "specific" 2>/dev/null || true
$WP option update woocommerce_specific_ship_to_countries "FR" 2>/dev/null || true
$WP option update woocommerce_shipping_cost_requires_address "yes" 2>/dev/null || true
$WP option update woocommerce_default_customer_address "base" 2>/dev/null || true

# --- Skip onboarding wizard completely ---
$WP option update woocommerce_onboarding_opt_in "no" 2>/dev/null || true
$WP option update woocommerce_onboarding_profile '{"skipped":true,"completed":true,"industry":[{"slug":"other"}],"product_types":["physical"],"product_count":"11-100","selling_venues":"no","business_extensions":[]}' --format=json 2>/dev/null || true
$WP option update woocommerce_task_list_hidden "yes" 2>/dev/null || true
$WP option update woocommerce_task_list_complete "yes" 2>/dev/null || true
$WP option update woocommerce_extended_task_list_hidden "yes" 2>/dev/null || true
$WP option update woocommerce_task_list_welcome_modal_dismissed "yes" 2>/dev/null || true
$WP option update woocommerce_task_list_prompt_shown "1" 2>/dev/null || true
$WP option update woocommerce_show_marketplace_suggestions "no" 2>/dev/null || true
$WP option update woocommerce_allow_tracking "no" 2>/dev/null || true
$WP option update woocommerce_merchant_email_notifications '{"enable":"no"}' --format=json 2>/dev/null || true
$WP option update woocommerce_admin_install_timestamp "1" 2>/dev/null || true
$WP option update wc_admin_show_legacy_coupon_menu "1" 2>/dev/null || true

# Mark all setup tasks as completed
$WP option update woocommerce_task_list_tracked_completed_tasks '["store_details","purchase","products","payments","tax","shipping","appearance"]' --format=json 2>/dev/null || true

# Dismiss all admin notices
$WP option update woocommerce_admin_notices '[]' --format=json 2>/dev/null || true
$WP option update woocommerce_no_sales_tax '{"dismissed":"yes"}' --format=json 2>/dev/null || true

# Disable WooCommerce marketing hub and suggestions
$WP option update woocommerce_show_marketplace_suggestions "no" 2>/dev/null || true
$WP option update woocommerce_ces_tracks_queue '[]' --format=json 2>/dev/null || true

# --- Permalinks (pretty URLs for products) ---
$WP rewrite structure '/%postname%/' 2>/dev/null || true
$WP option update woocommerce_permalinks '{"product_base":"/produit","category_base":"categorie-produit","tag_base":"etiquette-produit"}' --format=json 2>/dev/null || true

# ──────────────────────────────────────────────────────────────
# 5. Create WooCommerce pages
# ──────────────────────────────────────────────────────────────
echo "→ Creating WooCommerce pages..."
$WP wc --user=$ADMIN_ID tool run install_pages 2>/dev/null || true

# ──────────────────────────────────────────────────────────────
# 6. Create product categories (wp term — more reliable than wc API)
# ──────────────────────────────────────────────────────────────
echo "→ Creating product categories..."

get_or_create_cat() {
  local name="$1"
  local slug="$2"
  local parent_id="${3:-0}"

  # Check if exists
  local cat_id=$($WP term list product_cat --slug="$slug" --field=term_id --format=csv 2>/dev/null | head -1)
  if [ -n "$cat_id" ] && [ "$cat_id" != "term_id" ]; then
    echo "  ✓ $name exists (ID: $cat_id)" >&2
    echo "$cat_id"
    return
  fi

  # Create
  cat_id=$($WP term create product_cat "$name" --slug="$slug" --parent="$parent_id" --porcelain 2>/dev/null || echo "")
  if [ -n "$cat_id" ]; then
    echo "  ✓ Created: $name (ID: $cat_id)" >&2
    echo "$cat_id"
  else
    echo "  ⚠ Failed: $name" >&2
    echo "0"
  fi
}

# Top-level
CAT_BIJOUX=$(get_or_create_cat "Bijoux" "bijoux")
CAT_ACCESSOIRES=$(get_or_create_cat "Accessoires" "accessoires")
CAT_VETEMENTS=$(get_or_create_cat "Vêtements" "vetements")
CAT_MAISON=$(get_or_create_cat "Maison & Déco" "maison-deco")

# Sub: Bijoux
CAT_BRACELETS=$(get_or_create_cat "Bracelets" "bracelets" "$CAT_BIJOUX")
CAT_COLLIERS=$(get_or_create_cat "Colliers" "colliers" "$CAT_BIJOUX")
CAT_BAGUES=$(get_or_create_cat "Bagues" "bagues" "$CAT_BIJOUX")
CAT_BOUCLES=$(get_or_create_cat "Boucles d'oreilles" "boucles-oreilles" "$CAT_BIJOUX")

# Sub: Accessoires
CAT_SACS=$(get_or_create_cat "Sacs & Pochettes" "sacs-pochettes" "$CAT_ACCESSOIRES")
CAT_ECHARPES=$(get_or_create_cat "Écharpes & Foulards" "echarpes-foulards" "$CAT_ACCESSOIRES")

# Sub: Vêtements
CAT_TSHIRTS=$(get_or_create_cat "T-shirts" "t-shirts" "$CAT_VETEMENTS")
CAT_SWEATS=$(get_or_create_cat "Sweats & Pulls" "sweats-pulls" "$CAT_VETEMENTS")

# ──────────────────────────────────────────────────────────────
# 7. Create sample products (22 products, varied prices/stock)
# ──────────────────────────────────────────────────────────────
echo "→ Creating sample products..."

create_product() {
  local name="$1"
  local price="$2"
  local sku="$3"
  local desc="$4"
  local cat_id="$5"
  local stock="${6:-50}"
  local sale_price="$7"
  local weight="$8"

  # Skip if already exists
  local existing=$($WP post list --post_type=product --meta_key=_sku --meta_value="$sku" --format=count 2>/dev/null || echo "0")
  if [ "$existing" -gt "0" ]; then
    echo "  ✓ $name (exists)"
    return
  fi

  # Build command
  local cmd="$WP wc product create --user=$ADMIN_ID"
  cmd="$cmd --name=\"$name\" --regular_price=$price --sku=$sku"
  cmd="$cmd --status=publish --manage_stock=true --stock_quantity=$stock"

  if [ -n "$sale_price" ]; then
    cmd="$cmd --sale_price=$sale_price"
  fi
  if [ -n "$weight" ]; then
    cmd="$cmd --weight=$weight"
  fi

  cmd="$cmd --porcelain"

  local id=$(eval $cmd 2>/dev/null || echo "")

  if [ -n "$id" ] && [ "$id" -gt "0" ] 2>/dev/null; then
    # Set description
    $WP post update "$id" --post_excerpt="$desc" 2>/dev/null || true
    # Assign category
    if [ -n "$cat_id" ] && [ "$cat_id" -gt "0" ] 2>/dev/null; then
      $WP term set "$id" product_cat "$cat_id" 2>/dev/null || true
    fi
    local dp="$price€"
    if [ -n "$sale_price" ]; then dp="${sale_price}€ (was ${price}€)"; fi
    echo "  ✓ $name — $sku — $dp — stock:$stock"
  else
    echo "  ⚠ Failed: $name"
  fi
}

# --- Bracelets (3) ---
create_product "Bracelet Perles Naturelles" "29.90" "BPN-001" \
  "Bracelet en perles naturelles fait main, taille ajustable. Pierre: agate bleue." \
  "$CAT_BRACELETS" 50 "" "0.05"

create_product "Bracelet Jonc Doré" "39.00" "BJD-002" \
  "Bracelet jonc en plaqué or 18 carats, diamètre 6cm. Finition polie miroir." \
  "$CAT_BRACELETS" 30 "29.00" "0.03"

create_product "Bracelet Cuir Tressé Homme" "25.00" "BCT-003" \
  "Bracelet en cuir véritable tressé avec fermoir magnétique acier. 21cm." \
  "$CAT_BRACELETS" 80 "" "0.04"

# --- Colliers (3) ---
create_product "Collier Lune Dorée" "45.00" "CLD-004" \
  "Collier pendentif lune en plaqué or, chaîne 45cm réglable." \
  "$CAT_COLLIERS" 25 "" "0.02"

create_product "Collier Perle Baroque" "65.00" "CPB-005" \
  "Collier perle baroque eau douce sur chaîne argent 925. Pièce unique." \
  "$CAT_COLLIERS" 10 "52.00" "0.03"

create_product "Chaîne Maille Figaro" "35.00" "CMF-006" \
  "Chaîne maille figaro acier inoxydable doré, 50cm." \
  "$CAT_COLLIERS" 60 "" "0.04"

# --- Bagues (2) ---
create_product "Bague Fleur de Lotus" "19.90" "BFL-007" \
  "Bague ajustable argent 925, motif fleur de lotus. Taille 48-56." \
  "$CAT_BAGUES" 45 "" "0.01"

create_product "Bague Chevalière Onyx" "55.00" "BCO-008" \
  "Chevalière homme acier inoxydable, pierre onyx noire. Taille 60-68." \
  "$CAT_BAGUES" 20 "" "0.02"

# --- Boucles d'oreilles (2) ---
create_product "Boucles Étoiles Argent" "24.50" "BEA-009" \
  "Boucles oreilles étoiles argent 925. Fermoir poussette." \
  "$CAT_BOUCLES" 40 "" "0.01"

create_product "Créoles Dorées Larges" "32.00" "CDL-010" \
  "Créoles plaqué or diamètre 4cm. Style minimaliste chic." \
  "$CAT_BOUCLES" 35 "24.00" "0.01"

# --- Sacs & Pochettes (3) ---
create_product "Pochette Velours Noir" "15.00" "PVN-011" \
  "Pochette cadeau velours noir avec cordon doré. 12x9cm." \
  "$CAT_SACS" 200 "" "0.02"

create_product "Sac Bandoulière Cuir" "89.00" "SBC-012" \
  "Sac bandoulière cuir grainé. 22x15x6cm. Bandoulière réglable." \
  "$CAT_SACS" 15 "69.00" "0.35"

create_product "Trousse Maquillage Fleurie" "18.50" "TMF-013" \
  "Trousse toilette motif floral, doublure imperméable. 20x12x8cm." \
  "$CAT_SACS" 55 "" "0.08"

# --- Écharpes & Foulards (2) ---
create_product "Foulard Soie Imprimé" "42.00" "FSI-014" \
  "Foulard 100% soie, imprimé géométrique. 90x90cm. Made in France." \
  "$CAT_ECHARPES" 20 "" "0.06"

create_product "Écharpe Laine Mérinos" "55.00" "ELM-015" \
  "Écharpe laine mérinos extra-fine. 180x30cm. Coloris gris chiné." \
  "$CAT_ECHARPES" 12 "39.90" "0.12"

# --- T-shirts (2) ---
create_product "T-shirt Coton Bio Blanc" "28.00" "TCB-016" \
  "T-shirt unisexe coton bio GOTS. Col rond, coupe droite." \
  "$CAT_TSHIRTS" 100 "" "0.18"

create_product "T-shirt Oversize Noir" "32.00" "TON-017" \
  "T-shirt oversize unisexe noir, coton 220g. Logo minimaliste." \
  "$CAT_TSHIRTS" 75 "" "0.22"

# --- Sweats & Pulls (2) ---
create_product "Sweat Capuche Bleu Marine" "59.00" "SCB-018" \
  "Sweat capuche unisexe molleton brossé. Poche kangourou. 300g." \
  "$CAT_SWEATS" 40 "" "0.45"

create_product "Pull Col Roulé Crème" "49.90" "PCC-019" \
  "Pull col roulé maille fine, laine recyclée. Coupe Regular." \
  "$CAT_SWEATS" 0 "" "0.30"

# --- Maison & Déco (3) ---
create_product "Bougie Parfumée Ambre" "22.00" "BPA-020" \
  "Bougie artisanale ambre et bois de santal. Cire soja. 180g, 40h." \
  "$CAT_MAISON" 90 "" "0.25"

create_product "Diffuseur Huiles Essentielles" "38.00" "DHE-021" \
  "Diffuseur céramique blanche, bâtonnets rotin. Lavande. 200ml." \
  "$CAT_MAISON" 25 "29.90" "0.40"

create_product "Mug Artisanal Grès" "16.00" "MAG-022" \
  "Mug grès émaillé fait main, 350ml. Compatible lave-vaisselle." \
  "$CAT_MAISON" 65 "" "0.35"

# ──────────────────────────────────────────────────────────────
# 8. Create test customers
# ──────────────────────────────────────────────────────────────
echo "→ Creating test customers..."
$WP user create testclient testclient@hou.la \
  --role=customer --user_pass=test123 \
  --first_name=Marie --last_name=Martin 2>/dev/null \
  || echo "  ✓ testclient@hou.la already exists"

$WP user create vip-client vip@hou.la \
  --role=customer --user_pass=test123 \
  --first_name=Jean --last_name=Dupont 2>/dev/null \
  || echo "  ✓ vip@hou.la already exists"

# ──────────────────────────────────────────────────────────────
# 9. Configure payments
# ──────────────────────────────────────────────────────────────
echo "→ Configuring payments..."

# Enable Cash on Delivery (no Stripe needed)
$WP option update woocommerce_cod_settings \
  '{"enabled":"yes","title":"Paiement à la livraison","description":"Pour les tests locaux — aucun paiement réel.","instructions":"Commande test acceptée automatiquement.","enable_for_methods":[],"enable_for_virtual":"yes"}' \
  --format=json 2>/dev/null || true

# Enable Bank Transfer (BACS) for variety
$WP option update woocommerce_bacs_settings \
  '{"enabled":"yes","title":"Virement bancaire","description":"Pour tester le flux de paiement manuel.","instructions":"RIB fictif pour tests : FR76 0000 0000 0000 0000 0000 000","accounts":[{"account_name":"Hou.la Dev","bank_name":"Banque Test","sort_code":"","account_number":"0000000000","iban":"FR7600000000000000000000000","bic":"TESTFRPP"}]}' \
  --format=json 2>/dev/null || true

# Disable PayPal, Stripe etc. (not needed locally)
$WP option update woocommerce_cheque_settings '{"enabled":"no"}' --format=json 2>/dev/null || true
$WP option update woocommerce_paypal_settings '{"enabled":"no"}' --format=json 2>/dev/null || true

# ──────────────────────────────────────────────────────────────
# 10. Configure shipping zones & methods
# ──────────────────────────────────────────────────────────────
echo "→ Configuring shipping..."

# Create France zone with flat rate
ZONE_ID=$($WP wc shipping_zone create --user=$ADMIN_ID --name="France Métropolitaine" --order=1 --porcelain 2>/dev/null || echo "")
if [ -n "$ZONE_ID" ] && [ "$ZONE_ID" -gt "0" ] 2>/dev/null; then
  # Add FR location to zone
  $WP wc shipping_zone_location update $ZONE_ID --user=$ADMIN_ID --code="FR" --type="country" 2>/dev/null || true
  # Add flat rate method
  METHOD_ID=$($WP wc shipping_zone_method create $ZONE_ID --user=$ADMIN_ID --method_id="flat_rate" --porcelain 2>/dev/null || echo "")
  if [ -n "$METHOD_ID" ]; then
    $WP option update "woocommerce_flat_rate_${METHOD_ID}_settings" \
      '{"title":"Livraison standard","tax_status":"none","cost":"4.90"}' \
      --format=json 2>/dev/null || true
    echo "  ✓ France — Livraison standard 4,90€"
  fi
  # Add free shipping for orders > 50€
  FREE_ID=$($WP wc shipping_zone_method create $ZONE_ID --user=$ADMIN_ID --method_id="free_shipping" --porcelain 2>/dev/null || echo "")
  if [ -n "$FREE_ID" ]; then
    $WP option update "woocommerce_free_shipping_${FREE_ID}_settings" \
      '{"title":"Livraison gratuite","requires":"min_amount","min_amount":"50","ignore_discounts":"no"}' \
      --format=json 2>/dev/null || true
    echo "  ✓ France — Gratuit dès 50€"
  fi
else
  echo "  ⚠ Shipping zone already exists or creation failed"
fi

# Set "Rest of the world" fallback
$WP wc shipping_zone_method create 0 --user=$ADMIN_ID --method_id="flat_rate" --porcelain 2>/dev/null || true
$WP option update woocommerce_flat_rate_settings '{"title":"International","tax_status":"none","cost":"12.90"}' --format=json 2>/dev/null || true

# ──────────────────────────────────────────────────────────────
# 11. Disable unnecessary plugins & admin bloat
# ──────────────────────────────────────────────────────────────
echo "→ Cleaning up admin..."

# Dismiss all WooCommerce admin notices & inbox
$WP option update woocommerce_admin_notices '[]' --format=json 2>/dev/null || true
$WP eval '$notes = \Automattic\WooCommerce\Admin\Notes\Notes::delete_notes_with_name("wc-refund-returns-page"); echo "Done";' 2>/dev/null || true

# Deactivate Akismet & Hello Dolly if present
$WP plugin deactivate akismet 2>/dev/null || true
$WP plugin deactivate hello 2>/dev/null || true

# Set timezone
$WP option update timezone_string "Europe/Paris" 2>/dev/null || true
$WP option update date_format "j F Y" 2>/dev/null || true
$WP option update time_format "H:i" 2>/dev/null || true
$WP option update start_of_week "1" 2>/dev/null || true
$WP option update WPLANG "fr_FR" 2>/dev/null || true

# Install French language pack
$WP language core install fr_FR 2>/dev/null || true
$WP site switch-language fr_FR 2>/dev/null || true

echo ""
echo "=== ✅ Setup Complete ==="
echo ""
echo "  🌐 WordPress:  http://localhost:8080       (fr_FR)"
echo "  🔧 Admin:      http://localhost:8080/wp-admin  (admin / admin)"
echo "  🛒 Shop:       http://localhost:8080/boutique"
echo ""
echo "  Boutique configurée :"
echo "    • Devise : EUR (format français)"
echo "    • Livraison : Standard 4,90€ / Gratuit dès 50€ / International 12,90€"
echo "    • Paiements : Paiement à la livraison + Virement bancaire"
echo "    • Fuseau : Europe/Paris"
echo "    • Onboarding WooCommerce : désactivé"
echo ""
echo "  Hou.la plugin :"
echo "    • API URL : http://host.docker.internal:53001"
echo "    • Debug : activé"
echo "    • Auto-sync : activé"
echo "    • DEV_MODE : activé (via wp-config.php)"
echo "    → Lancer l'API locale avant de connecter :"
echo "      cd api && npm run start"
echo ""
echo "  Test customers:"
echo "    testclient@hou.la / test123  (Marie Martin)"
echo "    vip@hou.la        / test123  (Jean Dupont)"
echo ""
echo "  Categories (12):"
echo "    Bijoux → Bracelets, Colliers, Bagues, Boucles d'oreilles"
echo "    Accessoires → Sacs & Pochettes, Écharpes & Foulards"
echo "    Vêtements → T-shirts, Sweats & Pulls"
echo "    Maison & Déco"
echo ""
echo "  Products (22): prices 15€–89€, 6 on sale, 1 out of stock"
echo ""
echo "  Next steps:"
echo "  1. Start l'API en local :  cd api && npm run start"
echo "  2. Go to wp-admin → Hou.la → Connection tab"
echo "  3. Click 'Authorize' to connect to your local API"
echo "     (API URL already set to http://host.docker.internal:53001)"
echo "  4. Go to Boutique → add to cart → place order with COD"
echo "  5. Check Hou.la Print app for label printing"
echo ""
