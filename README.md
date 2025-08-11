# Webikos - Supabase Auth System

Moderní autentifikační systém s Supabase integrací pro přihlašování a registraci uživatelů.

## 🚀 Funkce

- ✅ **Email/Password přihlášení a registrace**
- ✅ **Social Auth** (Google, GitHub) - připraveno k nastavení
- ✅ **Responsivní design**
- ✅ **Real-time auth state management**
- ✅ **Bezpečné session handling**
- ✅ **Uživatelský dashboard**

## 🛠️ Technologie

- **Frontend**: Vanilla HTML, CSS, JavaScript
- **Backend**: Supabase (Auth, Database)
- **Hosting**: Vercel
- **Styling**: Custom CSS s moderním designem

## 📋 Nastavení

### 1. Supabase konfigurace
Projekt je již nakonfigurován s:
- **Project ID**: `gjfzmbeylksefphagupw`
- **URL**: `https://gjfzmbeylksefphagupw.supabase.co`
- **Region**: US East 1

### 2. Auth nastavení
- Email/password auth: ✅ Aktivní
- Site URL: `https://webikos-saukr.vercel.app`
- Email confirmations: ✅ Povoleno

### 3. Social Auth (volitelné)
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
