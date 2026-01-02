# Tranås Intranät

WordPress-plugin med intranätsanpassningar för Tranås kommun. Pluginet hanterar inloggningskrav, användarprofilfält och frontend-formulär.

## Funktioner

### 1. Inloggningskrav
Pluginet kräver inloggning för hela webbplatsen. Ej inloggade besökare visas en splash-screen med Tranås kommuns logotyp och en inloggningsknapp.

**Undantag från inloggningskrav:**
- Admin-sidor (`/wp-admin/`)
- Inloggningssidan (`wp-login.php`)
- REST API-anrop
- AJAX-anrop
- Cron-jobb

### 2. Anpassade användarfält
Pluginet lägger till följande fält i användarprofilen:

| Fält | Meta-nyckel | Typ |
|------|-------------|-----|
| Telefonnummer | `tranas_phone` | tel |
| Mobilnummer | `tranas_mobile` | tel |
| Snabbnummer | `tranas_quick_number` | text |

Fälten visas både i WordPress admin (användarprofilen) och via frontend-shortcoden.

### 3. Användarprofil-shortcode
Visar ett redigerbart profilformulär på frontend.

```
[tranas_user_profile]
```

**Attribut:**

| Attribut | Beskrivning | Exempel |
|----------|-------------|---------|
| `fields` | Kommaseparerad lista med fält att visa | `fields="first_name,last_name,user_email"` |
| `exclude` | Kommaseparerad lista med fält att exkludera | `exclude="description,user_url"` |

**Tillgängliga fält:**
- `user_login` (skrivskyddat)
- `user_email`
- `first_name`
- `last_name`
- `nickname`
- `display_name`
- `user_url`
- `description`
- `tranas_phone`
- `tranas_mobile`
- `tranas_quick_number`

**Exempel:**
```
[tranas_user_profile fields="first_name,last_name,tranas_phone,tranas_mobile"]
```

```
[tranas_user_profile exclude="user_url,description"]
```

### 4. Personligt nyhetsflöde

Låter användare anpassa sitt nyhetsflöde genom att välja kategorier.

#### Inställnings-shortcode

Visar ett formulär där användaren kan välja vilka kategorier som ska visas i nyhetsflödet:

```
[tranas_news_preferences]
```

**Attribut:**

| Attribut | Beskrivning | Standard |
|----------|-------------|----------|
| `taxonomy` | Taxonomi att visa kategorier från | `category` |
| `title` | Rubrik ovanför formuläret | `Anpassa ditt nyhetsflöde` |
| `description` | Beskrivning under rubriken | `Välj vilka kategorier du vill se...` |

**Exempel:**
```
[tranas_news_preferences title="Välj dina intressen" description="Markera de områden du vill följa"]
```

#### Nyhetsflödes-shortcode

Visar det personaliserade nyhetsflödet baserat på användarens val:

```
[tranas_news_feed]
```

**Attribut:**

| Attribut | Beskrivning | Standard |
|----------|-------------|----------|
| `posts_per_page` | Antal inlägg att visa | `10` |
| `post_type` | Post-typ att hämta | `post` |
| `taxonomy` | Taxonomi för kategorisering | `category` |
| `fallback` | Vad som visas om ingen personalisering: `all`, `none`, `latest` | `all` |
| `layout` | Layout: `list`, `grid`, `compact` | `list` |
| `show_excerpt` | Visa utdrag | `true` |
| `show_date` | Visa datum | `true` |
| `show_category` | Visa kategori-etiketter | `true` |
| `show_thumbnail` | Visa miniatyrbilder | `true` |
| `excerpt_length` | Antal ord i utdraget | `30` |
| `date_format` | Datumformat (PHP-format) | WP-inställning |
| `show_empty_message` | Visa meddelande om inga inlägg | `true` |
| `show_settings_link` | Visa länk till inställningar | `true` |
| `settings_url` | URL till inställningssidan | tom |

**Exempel:**
```
[tranas_news_feed posts_per_page="5" layout="grid" settings_url="/mina-installningar"]
```

```
[tranas_news_feed fallback="none" show_thumbnail="false" excerpt_length="20"]
```

**Fallback-alternativ:**
- `all` - Visar alla inlägg om användaren inte valt kategorier
- `none` - Visar ett meddelande om att välja kategorier
- `latest` - Visar senaste inläggen oavsett kategori

### 5. System (externa länkar)

Pluginet skapar en custom post type för "System" – länkar till externa system som används i organisationen.

#### Post-typ

System-posttypen (`tranas_system`) har:
- **Titel** – Systemets namn
- **Utvald bild** – Ikon/logotyp för systemet
- **Extern länk** (ACF-fält) – URL till det externa systemet

Posttypen använder klassisk editor (inte Gutenberg) för enkel hantering.

#### System-shortcode

Visar en lista med alla system som klickbara kort:

```
[tranas_system]
```

**Attribut:**

| Attribut | Beskrivning | Standard |
|----------|-------------|----------|
| `posts_per_page` | Antal system att visa (-1 = alla) | `-1` |
| `orderby` | Sorteringsfält: `title`, `date`, `menu_order` | `title` |
| `order` | Sorteringsriktning: `ASC`, `DESC` | `ASC` |
| `layout` | Layout: `grid`, `list` | `grid` |
| `columns` | Antal kolumner i grid-layout (1-6) | `3` |
| `show_thumbnail` | Visa miniatyrbild/ikon | `true` |
| `link_target` | Hur länken öppnas: `_blank`, `_self` | `_blank` |
| `title` | Rubrik ovanför systemlistan | `Mina system` |
| `show_title` | Visa rubriken | `true` |
| `edit_url` | URL till sida för att redigera system | tom |
| `edit_text` | Text på redigera-knappen | `Redigera mina system` |

**Exempel:**
```
[tranas_system columns="4" orderby="title" order="ASC"]
```

```
[tranas_system layout="list" show_thumbnail="false"]
```

```
[tranas_system edit_url="/mina-installningar" edit_text="Hantera system"]
```

```
[tranas_system show_title="false" posts_per_page="6"]
```

## Installation

1. Ladda upp plugin-mappen till `/wp-content/plugins/`
2. Aktivera pluginet via WordPress admin → Tillägg

## Krav

- WordPress 6.0 eller senare
- PHP 8.0 eller senare
- Advanced Custom Fields (ACF) – för System-posttypens fält

## Filter och hooks

### Anpassa splash-screen

```php
// Anpassad inloggnings-URL
add_filter( 'tranas_intranet_login_url', function( $url ) {
    return 'https://example.com/custom-login';
} );

// Anpassad titel
add_filter( 'tranas_intranet_splash_title', function( $title ) {
    return 'Välkommen till intranätet';
} );

// Anpassad beskrivning
add_filter( 'tranas_intranet_splash_description', function( $description ) {
    return 'Logga in med ditt kommunanvändarkonto.';
} );

// Anpassad knapptext
add_filter( 'tranas_intranet_splash_button_text', function( $text ) {
    return 'Logga in här';
} );
```

### Lägg till anpassade inloggningssidor

Om du har en anpassad inloggningssida som ska undantas från inloggningskravet:

```php
add_filter( 'tranas_intranet_login_pages', function( $pages ) {
    $pages[] = 123; // Sid-ID för anpassad inloggningssida
    return $pages;
} );
```

### Anpassa nyhetsflödets kategorier

```php
// Ändra vilka kategorier som visas i inställningarna
add_filter( 'tranas_news_feed_category_args', function( $args, $taxonomy ) {
    // Exkludera vissa kategorier
    $args['exclude'] = array( 1, 5, 10 );
    return $args;
}, 10, 2 );

// Anpassa query för nyhetsflödet
add_filter( 'tranas_news_feed_query_args', function( $query_args, $atts, $user_categories ) {
    // Lägg till sticky posts först
    $query_args['ignore_sticky_posts'] = false;
    return $query_args;
}, 10, 3 );
```

## CSS-klasser

Formulären använder följande CSS-klasser (kompatibla med Tranås Forms-temat):

### Profilformulär

| Klass | Beskrivning |
|-------|-------------|
| `.tranas-profile-form-wrapper` | Wrapper för hela formuläret |
| `.tranas-profile-form` | Formulärelementet |
| `.tranas-profile-section` | Fältsektioner (fieldset) |
| `.tranas-profile-field` | Enskilda fält |
| `.tf-message` | Meddelanden |
| `.tf-message--success` | Lyckade meddelanden |
| `.tf-message--error` | Felmeddelanden |
| `.tf-submit` | Submit-knappen |

### Nyhetsflödes-inställningar

| Klass | Beskrivning |
|-------|-------------|
| `.tranas-news-preferences-wrapper` | Wrapper för formuläret |
| `.tranas-news-preferences__categories` | Container för kategori-checkboxar |
| `.tranas-news-preferences__category` | Enskild kategori |
| `.tranas-news-preferences__checkbox` | Checkbox-element |
| `.tranas-news-preferences__select-all` | Markera alla-knappen |
| `.tranas-news-preferences__deselect-all` | Avmarkera alla-knappen |

### Nyhetsflöde

| Klass | Beskrivning |
|-------|-------------|
| `.tranas-news-feed` | Wrapper för nyhetsflödet |
| `.tranas-news-feed--list` | List-layout |
| `.tranas-news-feed--grid` | Grid-layout |
| `.tranas-news-feed--compact` | Kompakt layout |
| `.tranas-news-feed__item` | Enskilt nyhetsinlägg |
| `.tranas-news-feed__title` | Inläggets titel |
| `.tranas-news-feed__excerpt` | Utdrag |
| `.tranas-news-feed__date` | Datum |
| `.tranas-news-feed__category` | Kategorietikett |
| `.tranas-news-feed__thumbnail` | Miniatyrbild-container |
| `.tranas-news-feed__personalized-badge` | "Personligt flöde"-märke |

### Systemlista

| Klass | Beskrivning |
|-------|-------------|
| `.tranas-system` | Wrapper för systemlistan |
| `.tranas-system--grid` | Grid-layout |
| `.tranas-system--list` | List-layout |
| `.tranas-system--columns-X` | Antal kolumner (1-6) |
| `.tranas-system__header` | Header med rubrik och knapp |
| `.tranas-system__heading` | Rubrik (h2) |
| `.tranas-system__edit-link` | Redigera-knappen |
| `.tranas-system__list` | Container för systemkorten |
| `.tranas-system__item` | Enskilt systemkort (länk) |
| `.tranas-system__card` | Kortets innehåll |
| `.tranas-system__thumbnail` | Miniatyrbild-container |
| `.tranas-system__placeholder-icon` | Placeholder om ingen bild finns |
| `.tranas-system__content` | Textinnehåll |
| `.tranas-system__title` | Systemets titel |
| `.tranas-system__external-indicator` | Ikon för extern länk |

## JavaScript

Pluginet laddar följande JavaScript-filer:

**`assets/js/user-profile.js`**
- AJAX-inskick av profilformuläret
- Dynamisk uppdatering av visningsnamnsalternativ
- Tillgänglighetsanpassningar (ARIA-attribut)

**`assets/js/news-feed.js`**
- AJAX-sparning av nyhetsflödes-inställningar
- Markera/avmarkera alla kategorier

**JavaScript-objekt:**
```javascript
tranasIntranet = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    strings: {
        saving: 'Sparar...',
        saved: 'Uppgifterna har sparats!',
        error: 'Ett fel uppstod. Försök igen.'
    }
}
```

## Migrering

Vid aktivering migreras automatiskt gammal data:
- `tranas_card_number` → `tranas_quick_number`

## Changelog

### 1.2.0
- Nytt: Custom post type "System" för externa systemlänkar
- Ny shortcode: `[tranas_system]` för att visa systemlista
- ACF-fältgrupp för extern URL på System-posttypen
- Klassisk editor för System (ingen Gutenberg)

### 1.1.0
- Nytt: Personligt nyhetsflöde
- Ny shortcode: `[tranas_news_preferences]` för att välja kategorier
- Ny shortcode: `[tranas_news_feed]` för att visa personaliserat flöde
- Filter för anpassning av nyhetsflödets query och kategorier

### 1.0.0
- Initial release
- Inloggningskrav med splash-screen
- Anpassade användarfält (telefon, mobil, snabbnummer)
- Frontend-shortcode för användarprofil

## Support

Kontakta Tranås kommun för support och frågor.

---

*Plugin utvecklat för Tranås kommun*

