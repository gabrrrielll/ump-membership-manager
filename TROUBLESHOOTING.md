# 🔍 Checklist: De ce nu funcționează regulile automate?

## Verificări Obligatorii (în ordine)

### ✅ 1. Plugin-ul este activat?
**Verificare:**
- WordPress Admin → Plugins
- Caută "UMP Membership Manager"
- Trebuie să fie **activat** (nu doar instalat)

**Dacă e dezactivat:**
```
Activează plugin-ul
```

---

### ✅ 2. Indeed Ultimate Membership Pro (IHC) este activat?
**Verificare:**
- WordPress Admin → Plugins
- Caută "Indeed Ultimate Membership Pro"
- Trebuie să fie **activat**

**Dacă lipsește:**
```
Plugin-ul nostru DEPINDE de IHC
Fără IHC, nu funcționează nimic!
```

---

### ✅ 3. Există reguli automate salvate?
**Verificare:**
- WordPress Admin → Ultimate Membership Pro → Membership Manager
- Tab "Reguli Automate"
- Trebuie să existe cel puțin 1 regulă

**Exemplu regulă:**
```
Când user primește: Membership Premium (ID: 5)
Adaugă automat: Membership VIP Access (ID: 8)
```

**Dacă nu există reguli:**
```
1. Mergi la tab "Reguli Automate"
2. Selectează Membership Trigger
3. Selectează Membership Target
4. Click "Salvează Regula"
```

---

### ✅ 4. Memberships-urile sunt ACTIVE?
**Verificare:**
- WordPress Admin → Ultimate Membership Pro → Memberships
- Atât membership-ul TRIGGER cât și TARGET trebuie să aibă status = **Active**

**Dacă sunt inactive:**
```
Edit membership → Status → Set la "Active" → Save
```

---

### ✅ 5. Hook-ul IHC se declanșează?
**Verificare:**
- Activează WP_DEBUG în `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

- Atribuie manual un membership unui user
- Verifică `wp-content/debug.log`
- Caută linii care conțin "UMP MM:"

**Ce să cauți în log:**
```
✅ SUCCES:
UMP MM: Successfully added auto membership 8 to user 123 (triggered by membership 5)

❌ ERORI:
UMP MM: Failed to add auto membership 8 to user 123: [mesaj eroare]
UMP MM: Skipped auto rule - target membership 8 is not active
UMP MM: Skipped auto rule - already being processed (lock active)
```

**Dacă NU apar deloc mesaje "UMP MM:":**
```
Hook-ul nu se declanșează!
Cauze posibile:
1. IHC folosește alt hook (versiune diferită)
2. Plugin-ul nostru nu s-a inițializat
3. PHP Fatal error care oprește execuția
```

---

### ✅ 6. Versiunea IHC este compatibilă?
**Verificare:**
- Hook-ul `ihc_action_after_subscription_activated` există din IHC v9.0+
- Versiuni vechi pot folosi alt nume

**Test:**
```php
// Adaugă temporar în functions.php pentru test:
add_action('init', function() {
    global $wp_filter;
    error_log('IHC Hooks available: ' . print_r(array_keys($wp_filter), true));
});
```

**Caută în debug.log** hook-uri care conțin "ihc" și "subscription"

---

### ✅ 7. Nu există erori PHP?
**Verificare:**
- Verifică `wp-content/debug.log` pentru Fatal Errors
- Caută erori legate de "UMP_MM" sau "Indeed\Ihc"

**Erori comune:**
```
❌ Fatal error: Class 'Indeed\Ihc\UserSubscriptions' not found
   → IHC nu e activat sau versiune incompatibilă

❌ Call to undefined method
   → IHC API s-a schimbat, trebuie actualizat plugin-ul

❌ Maximum execution time exceeded
   → Circular dependency în reguli (A→B, B→A)
```

---

## 🧪 Test Manual

Rulează scriptul de debug:

1. **Uploads fișierul** `debug-auto-rules.php` în root-ul WordPress
2. **Accesează** în browser: `https://site-ul-tau.ro/debug-auto-rules.php`
3. **Verifică** toate punctele - toate trebuie ✅
4. **Șterge** fișierul după debugging (securitate)

---

## 🔧 Soluții pentru Problemele Comune

### Problema: "Hook-ul nu se declanșează"
**Cauză:** IHC folosește alt hook sau nu declanșează deloc

**Soluție:**
```php
// Test în functions.php (temporar):
add_action('all', function($hook) {
    if (strpos($hook, 'ihc') !== false || strpos($hook, 'subscription') !== false) {
        error_log('Hook detected: ' . $hook);
    }
});

// Atribuie un membership și verifică ce hook-uri apar în log
```

---

### Problema: "Regula se execută dar membership-ul nu se adaugă"
**Cauză:** IHC API returnează eroare

**Verificare log:**
```
UMP MM: Failed to add auto membership X to user Y: [MESAJ_EROARE]
```

**Soluții:**
- Verifică că user-ul există
- Verifică că membership-ul target e valid și activ
- Verifică că IHC acceptă adăugarea (nu e restricționat)

---

### Problema: "Funcționează dar doar prima dată"
**Cauză:** Lock-ul poate rămâne activ

**Soluție:**
```php
// Șterge lock-urile din baza de date:
DELETE FROM wp_options 
WHERE option_name LIKE '_transient_ump_mm_lock_%';
```

---

### Problema: "Circular dependency detected"
**Cauză:** Reguli care se apelează reciproc (A→B, B→A)

**Soluție:**
- Șterge una din reguli
- Sau restructurează: A→B, B→C (fără loop)

---

## 📞 Dacă Tot Nu Funcționează

1. **Colectează informații:**
   - Versiune WordPress
   - Versiune IHC
   - Versiune UMP MM
   - Error log complet
   - Screenshot reguli salvate

2. **Verifică:**
   - Alte plugin-uri care modifică IHC?
   - Theme custom care afectează hooks?
   - Server restrictions (memory, execution time)?

3. **Test în mediu curat:**
   - Dezactivează TOATE celelalte plugin-uri
   - Activează theme default (Twenty Twenty-Four)
   - Testează din nou

---

## ✅ Checklist Final

- [ ] Plugin UMP MM activat
- [ ] Plugin IHC activat
- [ ] Cel puțin 1 regulă salvată
- [ ] Ambele memberships (trigger + target) active
- [ ] WP_DEBUG activat
- [ ] Debug.log existent și writable
- [ ] Testat atribuire membership manual
- [ ] Verificat debug.log pentru mesaje "UMP MM:"
- [ ] Fără erori PHP fatale
- [ ] Fără circular dependencies

**Dacă TOATE sunt bifate și tot nu funcționează:**
→ Problema e la nivel de IHC hook incompatibilitate
→ Contactează dezvoltatorul pentru debugging avansat
