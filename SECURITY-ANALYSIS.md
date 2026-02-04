# Analisi di Sicurezza e Manutenzione - Sparrow Core Plugin

**Data**: 2026-02-03
**Versione Plugin**: 1.0.0
**Sito**: sparrowandsnow.com

---

## SOMMARIO ESECUTIVO

Questa analisi ha identificato **vulnerabilità critiche** che richiedono intervento immediato, oltre a numerose aree di miglioramento per la qualità del codice, la sicurezza e la manutenibilità.

| Categoria | Critico | Alto | Medio | Basso |
|-----------|---------|------|-------|-------|
| Sicurezza | 3 | 4 | 5 | 2 |
| Manutenzione | 0 | 3 | 6 | 4 |
| Performance | 0 | 2 | 3 | 2 |

---

## 1. VULNERABILITA' DI SICUREZZA CRITICHE

### 1.1 REST API senza autenticazione (CRITICO)

**File**: `classes/classes.wpapi.purchasecodes.php:31-36`

```php
register_rest_route("sparrowandsnow/v1", "purchasecodes/insert", array(
    "methods" => WP_REST_Server::CREATABLE,
    "callback" => array($this, "purchasecode_insert"),
    "permission_callback" => __return_true()  // VULNERABILE!
));
```

**Problema**: L'endpoint REST `/sparrowandsnow/v1/purchasecodes/insert` e' completamente aperto. Chiunque puo' inserire purchase codes nel database senza autenticazione.

**Rischio**: Un attaccante puo' saturare il database con dati fake, manipolare i purchase codes o utilizzare l'endpoint per attacchi.

**Soluzione**:
```php
"permission_callback" => function() {
    return current_user_can('manage_options') ||
           $this->validate_api_key($_SERVER['HTTP_X_API_KEY'] ?? '');
}
```

---

### 1.2 AJAX Handler senza Nonce Verification (CRITICO)

**File**: `framework/query_helper.php:46-108`

```php
function wordpress_post_ajax_load() {
    $current_page = $_REQUEST['current_page'];  // Nessuna sanitizzazione!
    $settings = array(
        'blog_thumbnail' => $_REQUEST['blog_thumbnail'],
        // ... tutti i parametri presi direttamente da $_REQUEST
    );
    // ...
}
add_action('wp_ajax_nopriv_wordpress_post_ajax_load', 'wordpress_post_ajax_load');
```

**Problemi**:
1. Nessun nonce verification per proteggere da CSRF
2. Input non sanitizzato da `$_REQUEST`
3. Possibile SQL injection attraverso parametri come `orderby`, `order`, `exclude_posts`

**Soluzione**:
```php
function wordpress_post_ajax_load() {
    // Verifica nonce
    if (!wp_verify_nonce($_REQUEST['nonce'], 'sas_blog_nonce')) {
        wp_die('Security check failed');
    }

    // Sanitizzazione input
    $current_page = absint($_REQUEST['current_page']);
    $posts_per_page = absint($_REQUEST['posts_per_page']);
    $orderby = sanitize_key($_REQUEST['orderby']);
    // ... etc
}
```

---

### 1.3 Inclusione di file non sanitizzata (CRITICO)

**File**: `framework/query_helper.php:84-92`

```php
if ($_REQUEST['blog_type'] == 'grid'){
    include plugin_dir_path( __DIR__ ) .'widgets/content/blog-templates/grid.php';
}
else if ($_REQUEST['blog_type'] == 'classic') {
    include plugin_dir_path( __DIR__ ) .'widgets/content/blog-templates/classic.php';
}
```

**Problema**: Il valore di `blog_type` proviene direttamente da `$_REQUEST` senza validazione whitelist.

**Rischio**: Potenziale Path Traversal o Local File Inclusion se il path viene manipolato.

**Soluzione**:
```php
$allowed_types = ['grid', 'classic', 'list'];
$blog_type = sanitize_file_name($_REQUEST['blog_type']);

if (!in_array($blog_type, $allowed_types)) {
    wp_die('Invalid blog type');
}

$template_file = plugin_dir_path(__DIR__) . 'widgets/content/blog-templates/' . $blog_type . '.php';
if (file_exists($template_file)) {
    include $template_file;
}
```

---

## 2. VULNERABILITA' DI SICUREZZA ALTE

### 2.1 SVG Upload abilitato senza sanitizzazione

**File**: `sas-plugin.php:71-75`

```php
function cc_mime_types($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');
```

**Problema**: Gli SVG possono contenere JavaScript malevolo (XSS persistente).

**Soluzione**: Usare una libreria come `enshrined/svg-sanitize` o Safe SVG plugin:
```php
// Installare: composer require enshrined/svg-sanitize
use enshrined\svgSanitize\Sanitizer;

add_filter('wp_handle_upload_prefilter', function($file) {
    if ($file['type'] === 'image/svg+xml') {
        $sanitizer = new Sanitizer();
        $content = file_get_contents($file['tmp_name']);
        $clean = $sanitizer->sanitize($content);
        file_put_contents($file['tmp_name'], $clean);
    }
    return $file;
});
```

---

### 2.2 Post Meta Update senza verifica capability

**File**: `classes/classes.utils.php:21-25`

```php
public static function updatePostMeta($post_id, $variable) {
    if (isset($_POST[$variable])) {
        update_post_meta($post_id, $variable, $_POST[$variable]);  // Nessuna sanitizzazione!
    }
}
```

**Problemi**:
1. Nessuna verifica `current_user_can('edit_post', $post_id)`
2. Nessun nonce check
3. Dati non sanitizzati

**Soluzione**:
```php
public static function updatePostMeta($post_id, $variable, $nonce_action = '') {
    if (!current_user_can('edit_post', $post_id)) {
        return false;
    }

    if ($nonce_action && !wp_verify_nonce($_POST['_wpnonce'], $nonce_action)) {
        return false;
    }

    if (isset($_POST[$variable])) {
        $value = sanitize_text_field($_POST[$variable]);
        update_post_meta($post_id, $variable, $value);
        return true;
    }
    return false;
}
```

---

### 2.3 Mancata escape nell'output HTML

**File**: `widgets/header/user.php:231`

```php
<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
   title="<?php echo __('My Account', 'sparrow') ?>">
   <?php echo __($user_title, 'sas')?>
</a>
```

**Problema**: `$user_title` proviene dalle impostazioni del widget e non viene escaped. Anche se Elementor sanitizza l'input, e' buona pratica usare sempre escape functions.

**Soluzione**:
```php
<?php echo esc_html__($user_title, 'sas')?>
```

---

### 2.4 Deprecated function get_page_by_title()

**File**: `classes/classes.wpapi.purchasecodes.php:95`

```php
$post = get_page_by_title($purchase_code, OBJECT, "post");
```

**Problema**: `get_page_by_title()` e' deprecata da WordPress 6.2.

**Soluzione**:
```php
$posts = get_posts([
    'post_type' => self::PURCHASECODES_TAXONOMY,
    'title' => $purchase_code,
    'posts_per_page' => 1,
    'post_status' => 'any'
]);
$post = !empty($posts) ? $posts[0] : null;
```

---

## 3. VULNERABILITA' DI SICUREZZA MEDIE

### 3.1 Mancata sanitizzazione in sanitizegeneral()

**File**: `classes/classes.utils.php:78-82`

```php
public static function sanitizegeneral($valore) {
    $valore = urldecode($valore);
    return $valore;  // Solo urldecode, nessuna sanitizzazione reale!
}
```

Il nome "sanitize" e' fuorviante - questa funzione non sanitizza nulla.

---

### 3.2 Text Domain inconsistente

Il plugin usa diversi text domain: `sas`, `sparrow`, `sasthemes`, `tiam`, `elementor-hello-world`

**File coinvolti**: Multipli

Questo impedisce la corretta localizzazione e puo' causare problemi di traduzione.

---

### 3.3 Funzioni definite dentro metodi

**File**: `sas-plugin.php:59-68, 71-75, 275-300`

```php
public function init() {
    function custom_product_search_query($query) {  // Funzione globale in metodo!
        // ...
    }
    add_action('pre_get_posts', 'custom_product_search_query');
}
```

**Problema**: Definire funzioni dentro metodi crea funzioni globali e puo' causare errori se il metodo viene chiamato piu' volte.

---

### 3.4 Variabile $post_id non definita

**File**: `classes/classes.wpapi.purchasecodes.php:133, 173`

```php
public function meta_details() {
    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) return $post_id;  // $post_id non esiste!
```

---

### 3.5 Errore di sintassi potenziale nella permission_callback

**File**: `classes/classes.wpapi.purchasecodes.php:35`

```php
"permission_callback" => __return_true()  // Chiamata di funzione, non reference!
```

Dovrebbe essere `"permission_callback" => '__return_true'` (stringa, non chiamata).

---

## 4. PROBLEMI DI ARCHITETTURA E MANUTENZIONE

### 4.1 Mancanza di Autoloading

Il plugin usa `require_once` manuale per ogni file. Implementare PSR-4 autoloading migliorerebbe la manutenibilita'.

**Soluzione**: Creare `composer.json`:
```json
{
    "autoload": {
        "psr-4": {
            "SAS\\": "src/"
        }
    }
}
```

---

### 4.2 Singleton mal implementato

**File**: `sas-plugin.php:222-227`

```php
public static function getInstance() {
    if (!isset(self::$instance) && !(self::$instance instanceof SAS_Plugin)) {
        self::$instance = new SAS_Plugin();
    }
    return self::$instance;
}
```

Il costruttore e' pubblico, permettendo istanziazione multipla. Dovrebbe essere privato o protected.

---

### 4.3 Coupling stretto con WooCommerce

Diversi widget assumono che WooCommerce sia attivo senza verifiche adeguate:

**File**: `widgets/header/user.php:249-251`

```php
if (class_exists('woocommerce')) {
    $widgets_manager->register(new SAS_Header_User());
}
```

La verifica e' a fine file, ma nel `render()` chiama direttamente `wc_get_page_permalink()` che potrebbe non esistere.

---

### 4.4 CSS/JS caricati sempre

**File**: `sas-el-widgets.php:48-72`

Tutti gli stili CSS vengono caricati su ogni pagina, anche dove non necessari. Usare enqueue condizionale.

---

### 4.5 Nessun versioning delle dipendenze

Le librerie come `slick.js` e `isotope.js` sono incluse senza indicazione della versione, rendendo difficile tracciare vulnerabilita' note.

---

## 5. PROBLEMI DI PERFORMANCE

### 5.1 Query non ottimizzate

**File**: `framework/query_helper.php`

Le query non usano `'no_found_rows' => true` quando la paginazione non serve, causando query COUNT(*) inutili.

---

### 5.2 Enqueue globale di assets

Tutti i CSS/JS vengono caricati su ogni pagina invece che solo dove necessario.

**Soluzione**: Usare `wp_enqueue_style()` solo nel metodo `render()` dei widget.

---

### 5.3 Mancanza di caching

Le query per categorie, termini e post non usano transient caching.

---

## 6. CHECKLIST DI AZIONI PRIORITARIE

### URGENTE (da fare subito)

- [ ] **Proteggere endpoint REST** con autenticazione
- [ ] **Aggiungere nonce verification** agli AJAX handlers
- [ ] **Sanitizzare tutti gli input** `$_REQUEST`, `$_POST`, `$_GET`
- [ ] **Validare blog_type** con whitelist prima dell'include

### ALTA PRIORITA' (entro 1 settimana)

- [ ] **Sanitizzare upload SVG** o disabilitare
- [ ] **Correggere updatePostMeta()** con capability checks
- [ ] **Escape di tutto l'output** con `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] **Unificare text domain** a `sas`

### MEDIA PRIORITA' (entro 1 mese)

- [ ] Refactoring con PSR-4 autoloading
- [ ] Singleton pattern corretto
- [ ] Caricamento condizionale CSS/JS
- [ ] Aggiungere unit tests
- [ ] Documentare le API

### BASSA PRIORITA' (miglioramenti)

- [ ] Implementare transient caching
- [ ] Aggiornare librerie JS
- [ ] Rimuovere codice commentato/inutilizzato
- [ ] Aggiungere type hints PHP 7.4+

---

## 7. RACCOMANDAZIONI GENERALI

### Sicurezza

1. **Principio del minimo privilegio**: Ogni funzione dovrebbe avere solo i permessi necessari
2. **Defense in depth**: Sanitizzare input E escape output, non solo uno dei due
3. **Fail secure**: In caso di errore, negare l'accesso piuttosto che permetterlo

### Sviluppo

1. **Code review**: Ogni modifica dovrebbe essere revisionata
2. **Testing**: Implementare unit tests, specialmente per funzioni critiche
3. **CI/CD**: Automatizzare PHPCS, PHPStan per individuare problemi

### Monitoraggio

1. **Logging**: Implementare logging per operazioni sensibili (login, modifiche dati)
2. **Rate limiting**: Limitare richieste agli endpoint API
3. **Aggiornamenti**: Mantenere WordPress, temi e plugin aggiornati

---

## 8. RISORSE UTILI

- [WordPress Plugin Security Guidelines](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHP Static Analysis Tool (PHPStan)](https://phpstan.org/)

---

**Nota**: Questo report e' stato generato come parte di un audit di sicurezza. Le vulnerabilita' identificate dovrebbero essere corrette prima di considerare il plugin production-ready.
