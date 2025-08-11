# Webikos - Twitter-like Social Platform

Moderní sociální platforma inspirovaná Twitterem s Supabase integrací.

## 🚀 Funkce

- ✅ **Email/Password přihlášení a registrace**
- ✅ **Uživatelská jména** (@username systém)
- ✅ **Twitter-like interface** s timeline
- ✅ **Psaní postů** (max 280 znaků)
- ✅ **Like systém** s real-time počítadlem
- ✅ **Uživatelské profily** s bio a avatary
- ✅ **Responsivní design**
- ✅ **Real-time auth state management**
- ✅ **Bezpečné session handling**
- ✅ **Row Level Security (RLS)**

## 🛠️ Technologie

- **Frontend**: Vanilla HTML, CSS, JavaScript
- **Backend**: Supabase (Auth, Database, RLS)
- **Database**: PostgreSQL s automatickými triggery
- **Hosting**: Vercel
- **Styling**: Custom CSS s Twitter-like designem

## 📋 Nastavení

### 1. Supabase konfigurace
Projekt je již nakonfigurován s:
- **Project ID**: `gjfzmbeylksefphagupw`
- **URL**: `https://gjfzmbeylksefphagupw.supabase.co`
- **Region**: US East 1

### 2. Databázové tabulky
- **user_profiles**: Uživatelské profily s @username
- **posts**: Tweety/posty (max 280 znaků)
- **post_likes**: Like systém s automatickým počítáním
- **RLS policies**: Bezpečnostní pravidla pro všechny tabulky

### 3. Auth nastavení
- Email/password auth: ✅ Aktivní
- Site URL: `https://webikos-saukr.vercel.app`
- Email confirmations: ✅ Povoleno
- Automatické vytváření profilů: ✅ Aktivní

### 4. Hlavní funkce

**📝 Psaní postů:**
- Compose box s počítadlem znaků (280 max)
- Real-time validace
- Automatické přidání do timeline

**👤 Uživatelské profily:**
- @username systém (3-20 znaků)
- Zobrazované jméno
- Bio text
- Automatické avatary z iniciál

**❤️ Interakce:**
- Like/Unlike posty
- Real-time počítadla
- Hover efekty

### 5. Social Auth (volitelné)
Pro aktivaci Google/GitHub přihlášení:

1. **Google OAuth**:
   - Jděte do [Google Cloud Console](https://console.cloud.google.com/)
   - Vytvořte OAuth 2.0 credentials
   - Přidejte redirect URI: `https://gjfzmbeylksefphagupw.supabase.co/auth/v1/callback`
   - Nastavte v Supabase Dashboard

2. **GitHub OAuth**:
   - Jděte do GitHub Settings > Developer settings > OAuth Apps
   - Vytvořte novou OAuth App
   - Authorization callback URL: `https://gjfzmbeylksefphagupw.supabase.co/auth/v1/callback`
   - Nastavte v Supabase Dashboard

## 🎯 Použití

### Základní přihlášení
```javascript
// Přihlášení
const { data, error } = await supabase.auth.signInWithPassword({
    email: 'user@example.com',
    password: 'password123'
})

// Registrace
const { data, error } = await supabase.auth.signUp({
    email: 'user@example.com',
    password: 'password123'
})
```

### Social přihlášení
```javascript
// Google
await supabase.auth.signInWithOAuth({
    provider: 'google',
    options: { redirectTo: window.location.origin }
})

// GitHub
await supabase.auth.signInWithOAuth({
    provider: 'github',
    options: { redirectTo: window.location.origin }
})
```

## 🔒 Bezpečnost

- **Row Level Security (RLS)**: Připraveno k implementaci
- **JWT tokeny**: Automatické obnovování
- **HTTPS**: Vynuceno pro všechny požadavky
- **Email verifikace**: Aktivní pro nové účty

## 📱 Responsivní design

Aplikace je plně responsivní a funguje na:
- 📱 Mobilních zařízeních
- 💻 Tabletech
- 🖥️ Desktop počítačích

## 🚀 Deployment

Projekt je automaticky deployován na Vercel:
- **URL**: https://webikos-saukr.vercel.app/
- **Auto-deploy**: Při push na main branch

## 📝 Další kroky

1. **Databáze**: Přidání uživatelských profilů
2. **RLS**: Implementace bezpečnostních politik
3. **Social Auth**: Dokončení Google/GitHub nastavení
4. **UI/UX**: Rozšíření dashboardu
5. **Notifikace**: Email templates customizace

## 🐛 Troubleshooting

### Časté problémy:
- **Email nechodí**: Zkontrolujte SMTP nastavení v Supabase
- **Social auth nefunguje**: Ověřte OAuth credentials
- **CORS chyby**: Zkontrolujte allowed origins v Supabase

## 📞 Podpora

Pro technickou podporu kontaktujte vývojáře nebo vytvořte issue v GitHub repository.

---

**Vytvořeno s ❤️ pomocí Supabase a Vercel**
