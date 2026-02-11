# 🔒 KEAMANAN & KETENTUAN PENTING

## ⚠️ FILE YANG TIDAK BOLEH DI-PUSH KE GITHUB

### 1. **File Konfigurasi Sensitif**

#### ❌ `.env` (WAJIB DIRAHASIAKAN)
File konfigurasi environment yang berisi:
- Password database
- API keys & secrets
- SMTP credentials
- Encryption keys
- Session secrets

**BAHAYA jika ter-push:**
- Database bisa diretas
- Email system dibobol
- Session hijacking
- Data user dicuri

**Yang harus dilakukan:**
- ✅ Sudah ada di `.gitignore`
- ✅ Gunakan `.env.example` sebagai template (tanpa value sensitif)
- ✅ Setiap developer punya `.env` sendiri yang tidak di-commit

#### ❌ `.env.backup`, `.env.production`, `.env.local`
Backup atau variant file `.env` — **semua harus dirahasiakan**

---

### 2. **Folder dengan Data Sensitif**

#### ❌ `/vendor/`
- Berisi semua dependencies Composer
- Size besar (100+ MB)
- Bisa di-generate ulang dengan `composer install`
- **Sudah di `.gitignore`** ✅

#### ❌ `/node_modules/`
- Berisi dependencies NPM/Node.js
- Size sangat besar (500+ MB)
- Bisa di-generate ulang dengan `npm install`
- **Sudah di `.gitignore`** ✅

#### ❌ `/storage/*.key`
- Encryption keys Laravel
- SSH private keys
- SSL certificates private key
- **Sudah di `.gitignore`** ✅

#### ❌ `/storage/logs/*.log`
- Log files bisa contain sensitive data
- IP addresses, passwords yang ter-log by mistake
- **Sudah di `.gitignore`** (`*.log`) ✅

#### ❌ `/public/storage/`
- Uploaded files dari user
- Bisa contain data pribadi
- **Sudah di `.gitignore`** ✅

---

### 3. **File Development Tools**

#### ❌ IDE Configuration Files
- `/.idea/` (PHPStorm/IntelliJ)
- `/.vscode/` (Visual Studio Code)
- `/.fleet/` (JetBrains Fleet)
- `/.zed/` (Zed editor)
- `/.nova/` (Nova editor)
- **Semua sudah di `.gitignore`** ✅

Alasan: Konfigurasi pribadi developer, tidak perlu dibagikan

#### ❌ `/auth.json`
- Composer authentication credentials
- Private repository tokens
- **Sudah di `.gitignore`** ✅

---

### 4. **File Cache & Temporary**

#### ❌ Cache Files
- `/.phpunit.cache/`
- `.phpunit.result.cache`
- `.phpactor.json`
- **Sudah di `.gitignore`** ✅

#### ❌ OS Files
- `.DS_Store` (macOS)
- `Thumbs.db` (Windows)
- **Sudah di `.gitignore`** ✅

---

## 🛡️ FILE YANG AMAN DI-PUSH KE GITHUB

### ✅ File Template & Example

#### `.env.example`
Template file environment tanpa value sensitif:
```env
APP_NAME=SPBE
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spbe
DB_USERNAME=root
DB_PASSWORD=

# Catatan: Ini hanya template!
# Copy ke .env dan isi value sebenarnya
```

**PENTING:** Pastikan `.env.example` tidak berisi:
- ❌ Password asli
- ❌ API key asli
- ❌ Token/secret asli
- ❌ Production URL/domain
- ❌ Email credentials

---

## 🚨 JIKA FILE SENSITIF TER-PUSH (ACCIDENT)

### Langkah Darurat:

1. **JANGAN PANIK** — tapi bertindak cepat!

2. **Langsung ganti semua credentials:**
   ```bash
   # Ganti password database
   # Ganti APP_KEY
   php artisan key:generate --force
   # Ganti semua API keys
   # Ganti password email SMTP
   ```

3. **Hapus dari Git History:**
   ```bash
   # Hapus file dari git history (DANGER!)
   git filter-branch --force --index-filter \
     "git rm --cached --ignore-unmatch .env" \
     --prune-empty --tag-name-filter cat -- --all

   # Force push (WARNING: Destructive!)
   git push origin --force --all
   ```

4. **Atau lebih mudah, gunakan BFG Repo-Cleaner:**
   ```bash
   # Download BFG dari https://rtyley.github.io/bfg-repo-cleaner/
   java -jar bfg.jar --delete-files .env
   git reflog expire --expire=now --all
   git gc --prune=now --aggressive
   git push origin --force --all
   ```

5. **Inform team:**
   - Beritahu semua developer
   - Pastikan semua pull ulang dari remote
   - Verifikasi file sensitif sudah hilang

---

## ✅ CHECKLIST SEBELUM PUSH KE GITHUB

```markdown
- [ ] File .env tidak ada di staged files
- [ ] File .env.backup tidak ada di staged files
- [ ] Folder /vendor/ tidak ter-commit
- [ ] Folder /node_modules/ tidak ter-commit
- [ ] Tidak ada password hardcoded di code
- [ ] Tidak ada API key hardcoded di code
- [ ] File .gitignore sudah up-to-date
- [ ] File .env.example sudah update tapi tanpa value sensitif
- [ ] Tidak ada file *.log ter-commit
- [ ] Tidak ada database backup (.sql) ter-commit
```

**Cara cek sebelum push:**
```bash
# Lihat file apa saja yang akan di-push
git status

# Lihat detail perubahan
git diff --staged

# Pastikan .env tidak ada
git ls-files | grep .env
# Output harus kosong atau hanya .env.example
```

---

## 📚 BEST PRACTICES

### 1. **Double Check Sebelum Push**
```bash
# Selalu review file yang akan di-commit
git status
git diff

# Jangan gunakan git add .
# Lebih baik spesifik:
git add app/
git add config/
git add routes/
```

### 2. **Gunakan Pre-commit Hook**
Buat file `.git/hooks/pre-commit`:
```bash
#!/bin/sh
# Cek jika .env ter-commit
if git diff --cached --name-only | grep -q "^\.env$"; then
    echo "ERROR: .env file should not be committed!"
    echo "Please remove it from staging: git reset .env"
    exit 1
fi
```

### 3. **Educate Team**
- Brief semua developer tentang security
- Review setiap pull request
- Setup branch protection di GitHub

### 4. **Monitoring**
- Aktifkan GitHub Secret Scanning
- Gunakan tools seperti GitGuardian
- Set up alerts untuk sensitive data exposure

---

## 📞 KONTAK DARURAT

Jika terjadi security incident:
1. Langsung hubungi Admin/Team Lead
2. Ganti semua credentials immediately
3. Document incident untuk post-mortem

---

## 📖 RESOURCES

- [GitHub: Removing sensitive data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/)
- [Laravel: Configuration](https://laravel.com/docs/configuration)
- [OWASP: Secrets Management](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)

---

**Last Updated:** 11 Februari 2026
**Maintained by:** SPBE Development Team
