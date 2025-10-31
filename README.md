# UMP Membership Manager

Plugin WordPress care extinde funcționalitățile pluginului **Indeed Ultimate Membership Pro**, oferind:

1. **Gestionare Users după Membership** - Căutare și adăugare în bulk a membreships la utilizatori
2. **Reguli Automate** - Adăugare automată a unui membership când un user primește un anumit membership activ

## Cerințe

- WordPress 5.0+
- PHP 7.0+
- **Indeed Ultimate Membership Pro** (plugin activat)

## Instalare

1. Copiază folderul `ump-membership-manager` în directorul `wp-content/plugins/`
2. Activează pluginul din panoul de administrare WordPress
3. Asigură-te că **Indeed Ultimate Membership Pro** este instalat și activat

## Utilizare

### 1. Căutare și Gestionare Users

1. Mergi la **Ultimate Membership Pro > Membership Manager**
2. Selectează un membership din dropdown
3. Click pe **"Caută Utilizatori"** pentru a găsi toți utilizatorii care au acel membership activ
4. Selectează utilizatorii din listă (sau folosește "Select All")
5. Alege un alt membership de adăugat
6. Click pe **"Adaugă la Utilizatorii Selectați"**

**Notă:** Pluginul va adăuga doar membreships active. Dacă un membership este inactiv (status != 0), nu va fi adăugat.

### 2. Reguli Automate

1. Mergi la tab-ul **"Reguli Automate"**
2. Selectează:
   - **Membership Trigger**: Membership-ul care, când devine activ, va declanșa regula
   - **Membership Adăugat**: Membership-ul care va fi adăugat automat
3. Click pe **"Salvează Regula"**

**Important:**
- Regula se aplică doar când membership-ul trigger devine **activ**
- Membership-ul adăugat trebuie să fie **activ** (altfel va fi ignorat)
- Dacă utilizatorul are deja membership-ul target activ, nu va fi adăugat din nou

## Funcționalități

### Verificare Membreships Active

Pluginul verifică întotdeauna că:
- Memberships afișate în dropdown-uri sunt doar cele active (status = 0 în IHC)
- Memberships nu sunt adăugate dacă sunt inactive
- Regulile automate nu se aplică pentru memberships inactive

### Hook-uri WordPress

Pluginul folosește hook-ul `ihc_action_after_subscription_activated` din IHC pentru a detecta când un membership devine activ și a aplica regulile automate.

### Logging

Erorile și acțiunile importante sunt loggate în error log-ul WordPress pentru debugging:
- Succese în adăugarea automată de memberships
- Erori în aplicarea regulilor
- Skip-uri când membership-ul target este inactiv

## Structură Fișiere

```
ump-membership-manager/
├── ump-membership-manager.php  # Main plugin file
├── includes/
│   ├── class-admin.php         # Admin interface
│   ├── class-auto-rules.php    # Auto rules handler
│   └── class-helper.php        # Helper functions
├── assets/
│   ├── admin.js                # Admin JavaScript
│   └── admin.css               # Admin styles
└── README.md                   # Documentation
```

## Securitate

- Toate acțiunile AJAX sunt protejate cu nonces
- Verificare permisiuni (`manage_options`)
- Sanitizare și validare a tuturor inputurilor
- Prevenire acces direct la fișiere PHP

## Dezvoltare

### Clase Principale

- **UMP_Membership_Manager**: Clasă principală a pluginului
- **UMP_MM_Admin**: Gestionează interfața admin și AJAX handlers
- **UMP_MM_Auto_Rules**: Gestionează aplicarea automată a regulilor
- **UMP_MM_Helper**: Funcții helper pentru lucrul cu IHC API

### Customizare

Pluginul poate fi extins folosind hook-uri WordPress standard sau prin modificarea claselor din folderul `includes/`.

## Suport

Pentru probleme sau întrebări, verifică:
1. Că IHC este activat și funcționează corect
2. Că memberships-urile sunt active (status = 0)
3. Log-urile WordPress pentru erori

## Licență

Licență personalizată - folosit intern.

