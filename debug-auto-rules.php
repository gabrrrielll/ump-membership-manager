<?php
/**
 * Debug Script pentru UMP Membership Manager Auto Rules
 * 
 * Pune acest fișier în root-ul WordPress și accesează-l din browser
 * pentru a verifica de ce regulile automate nu funcționează.
 */

// Load WordPress
require_once('wp-load.php');

// Verifică dacă user-ul e admin
if (!current_user_can('manage_options')) {
    die('Trebuie să fii administrator pentru a rula acest script.');
}

echo '<h1>🔍 UMP Membership Manager - Debug Auto Rules</h1>';
echo '<style>body{font-family:monospace;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;}</style>';

// TEST 1: Plugin activat?
echo '<h2>1. ✓ Verificare Plugin Activat</h2>';
if (class_exists('UMP_Membership_Manager')) {
    echo '<p class="success">✅ Plugin-ul UMP Membership Manager este activat</p>';
} else {
    echo '<p class="error">❌ Plugin-ul UMP Membership Manager NU este activat!</p>';
    echo '<p>Activează plugin-ul din WordPress Admin → Plugins</p>';
    die();
}

// TEST 2: IHC activat?
echo '<h2>2. ✓ Verificare IHC (Indeed Ultimate Membership Pro)</h2>';
if (class_exists('Indeed\\Ihc\\Db\\Memberships')) {
    echo '<p class="success">✅ IHC este activat și funcțional</p>';
} else {
    echo '<p class="error">❌ IHC NU este activat sau nu există clasa Memberships!</p>';
    echo '<p>Activează Indeed Ultimate Membership Pro</p>';
    die();
}

// TEST 3: Hook înregistrat?
echo '<h2>3. ✓ Verificare Hook Înregistrat</h2>';
global $wp_filter;
if (isset($wp_filter['ihc_action_after_subscription_activated'])) {
    echo '<p class="success">✅ Hook-ul "ihc_action_after_subscription_activated" este înregistrat</p>';
    echo '<pre>';
    print_r($wp_filter['ihc_action_after_subscription_activated']);
    echo '</pre>';
} else {
    echo '<p class="error">❌ Hook-ul NU este înregistrat!</p>';
    echo '<p>Problema: UMP_MM_Auto_Rules nu se inițializează corect.</p>';
}

// TEST 4: Clasa Auto Rules există?
echo '<h2>4. ✓ Verificare Clasa Auto Rules</h2>';
if (class_exists('UMP_MM_Auto_Rules')) {
    echo '<p class="success">✅ Clasa UMP_MM_Auto_Rules există</p>';
    
    // Verifică dacă metoda există
    if (method_exists('UMP_MM_Auto_Rules', 'handle_subscription_activated')) {
        echo '<p class="success">✅ Metoda handle_subscription_activated() există</p>';
    } else {
        echo '<p class="error">❌ Metoda handle_subscription_activated() NU există!</p>';
    }
} else {
    echo '<p class="error">❌ Clasa UMP_MM_Auto_Rules NU există!</p>';
}

// TEST 5: Reguli salvate?
echo '<h2>5. ✓ Verificare Reguli Automate Salvate</h2>';
$auto_rules = get_option('ump_mm_auto_rules', array());
if (empty($auto_rules)) {
    echo '<p class="warning">⚠️ NU există reguli automate salvate în baza de date!</p>';
    echo '<p>Mergi în WordPress Admin → Ultimate Membership Pro → Membership Manager → Reguli Automate</p>';
    echo '<p>și adaugă cel puțin o regulă.</p>';
} else {
    echo '<p class="success">✅ Există ' . count($auto_rules) . ' reguli salvate:</p>';
    echo '<pre>';
    print_r($auto_rules);
    echo '</pre>';
}

// TEST 6: Memberships active?
echo '<h2>6. ✓ Verificare Memberships Active</h2>';
if (class_exists('UMP_MM_Helper')) {
    $memberships = UMP_MM_Helper::get_active_memberships();
    if (empty($memberships)) {
        echo '<p class="warning">⚠️ NU există memberships active în IHC!</p>';
        echo '<p>Creează și activează memberships în IHC</p>';
    } else {
        echo '<p class="success">✅ Există ' . count($memberships) . ' memberships active:</p>';
        echo '<pre>';
        print_r($memberships);
        echo '</pre>';
    }
} else {
    echo '<p class="error">❌ Clasa UMP_MM_Helper NU există!</p>';
}

// TEST 7: Debug logging activat?
echo '<h2>7. ✓ Verificare Debug Logging WordPress</h2>';
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo '<p class="success">✅ WP_DEBUG este activat</p>';
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        echo '<p class="success">✅ WP_DEBUG_LOG este activat - verifică wp-content/debug.log</p>';
    } else {
        echo '<p class="warning">⚠️ WP_DEBUG_LOG nu este activat</p>';
        echo '<p>Adaugă în wp-config.php: define("WP_DEBUG_LOG", true);</p>';
    }
} else {
    echo '<p class="warning">⚠️ WP_DEBUG nu este activat</p>';
    echo '<p>Pentru debugging, adaugă în wp-config.php:</p>';
    echo '<pre>define("WP_DEBUG", true);\ndefine("WP_DEBUG_LOG", true);\ndefine("WP_DEBUG_DISPLAY", false);</pre>';
}

// TEST 8: Simulare hook (opțional - comentat)
echo '<h2>8. 🧪 Test Manual Hook</h2>';
echo '<p>Pentru a testa manual, descomenteaza codul de mai jos și refreshuiește pagina:</p>';
echo '<pre style="background:#ffe;border:2px solid orange;padding:15px;">';
echo '// Uncomment pentru test:
/*
if (!empty($auto_rules)) {
    $user_id = 1; // Schimbă cu ID-ul unui user real
    $membership_id = 5; // Schimbă cu ID-ul unui membership care are regulă
    
    echo "&lt;p&gt;Declanșez manual hook-ul pentru user_id=$user_id, membership_id=$membership_id&lt;/p&gt;";
    do_action("ihc_action_after_subscription_activated", $user_id, $membership_id, true, array());
    echo "&lt;p&gt;Hook declanșat! Verifică în debug.log dacă s-a executat regula.&lt;/p&gt;";
}
*/
';
echo '</pre>';

// REMEDII COMUNE
echo '<h2>💡 Remedii Comune</h2>';
echo '<ol>';
echo '<li><strong>Plugin dezactivat</strong> - Activează UMP Membership Manager din Plugins</li>';
echo '<li><strong>IHC dezactivat</strong> - Activează Indeed Ultimate Membership Pro</li>';
echo '<li><strong>Fără reguli</strong> - Adaugă reguli în Membership Manager → Reguli Automate</li>';
echo '<li><strong>Hook nu se declanșează</strong> - IHC nu folosește hook-ul standard, verifică versiunea IHC</li>';
echo '<li><strong>Erori PHP</strong> - Verifică wp-content/debug.log pentru erori</li>';
echo '<li><strong>Cache</strong> - Golește cache-ul WordPress/server</li>';
echo '<li><strong>Permisiuni fișiere</strong> - Verifică că PHP poate scrie în debug.log</li>';
echo '</ol>';

echo '<h2>📝 Următorii Pași</h2>';
echo '<ol>';
echo '<li>Verifică toate punctele de mai sus - toate trebuie să fie ✅</li>';
echo '<li>Activează WP_DEBUG și WP_DEBUG_LOG</li>';
echo '<li>Cumpără/atribuie manual un membership unui user</li>';
echo '<li>Verifică wp-content/debug.log pentru mesaje "<strong>UMP MM:</strong>"</li>';
echo '<li>Dacă nu vezi mesaje, hook-ul nu se declanșează - posibil versiune IHC diferită</li>';
echo '</ol>';

echo '<hr>';
echo '<p><em>Debug script creat de UMP Membership Manager v1.1.0</em></p>';
