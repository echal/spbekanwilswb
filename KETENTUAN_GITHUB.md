# 🚫 KETENTUAN PENTING - JANGAN PUSH KE GITHUB!

## ⚠️ FILE & FOLDER YANG DILARANG DI-PUSH

### 🔴 LEVEL CRITICAL (Keamanan Tinggi)

#### 1. **`.env`** - File Environment
```
❌ DILARANG KERAS!
```
**Berisi:**
- Password database
- APP_KEY (encryption key)
- Email password
- API keys & secrets

**Bahaya jika bocor:**
- 💀 Database bisa diretas
- 💀 Session bisa dibajak
- 💀 Email system dibobol
- 💀 Data user dicuri

**Status:** ✅ Sudah di `.gitignore`

---

#### 2. **`/storage/*.key`** - Encryption Keys
```
❌ DILARANG!
```
**Berisi:**
- Private keys
- SSL certificates
- OAuth keys

**Status:** ✅ Sudah di `.gitignore`

---

#### 3. **`/auth.json`** - Composer Auth
```
❌ DILARANG!
```
**Berisi:**
- GitHub tokens
- Packagist credentials

**Status:** ✅ Sudah di `.gitignore`

---

### 🟡 LEVEL HIGH (Efisiensi & Privasi)

#### 4. **`/vendor/`** - Composer Dependencies
```
⚠️ JANGAN DI-PUSH (Ukuran besar)
```
**Alasan:**
- Size: 50-150 MB
- Bisa rebuild: `composer install`
- Bikin repo lambat

**Status:** ✅ Sudah di `.gitignore`

---

#### 5. **`/node_modules/`** - NPM Dependencies
```
⚠️ JANGAN DI-PUSH (Ukuran sangat besar)
```
**Alasan:**
- Size: 200-500 MB
- Bisa rebuild: `npm install`
- Ribuan file, bikin GitHub lambat

**Status:** ✅ Sudah di `.gitignore`

---

#### 6. **`*.log`** - Log Files
```
⚠️ JANGAN DI-PUSH (Bisa contain sensitive data)
```
**Berisi:**
- Error messages (bisa bocorkan path)
- IP addresses
- User data yang ter-log
- SQL queries

**Status:** ✅ Sudah di `.gitignore`

---

#### 7. **`/public/storage/`** - Uploaded Files
```
⚠️ JANGAN DI-PUSH (Data user)
```
**Berisi:**
- User uploads (foto, dokumen)
- Data pribadi
- Size bisa besar

**Status:** ✅ Sudah di `.gitignore`

---

#### 8. **`*.sql`, `*.sqlite`** - Database Backups
```
❌ DILARANG KERAS!
```
**Berisi:**
- Seluruh data aplikasi
- Password users (meski di-hash)
- Data pribadi

**Status:** ✅ Sudah di `.gitignore`

---

### 🟢 LEVEL MEDIUM (Optional tapi Recommended)

#### 9. **IDE Configuration**
```
/.idea/      (PHPStorm)
/.vscode/    (VS Code)
/.fleet/     (Fleet)
/.zed/       (Zed)
```
**Alasan:**
- Konfigurasi pribadi developer
- Tidak perlu dibagikan

**Status:** ✅ Sudah di `.gitignore`

---

#### 10. **OS Files**
```
.DS_Store    (macOS)
Thumbs.db    (Windows)
desktop.ini  (Windows)
```
**Alasan:**
- File system OS
- Tidak ada gunanya di repo

**Status:** ✅ Sudah di `.gitignore`

---

## ✅ FILE YANG AMAN & WAJIB DI-PUSH

### 1. **`.env.example`**
```
✅ AMAN - Ini template tanpa value sensitif
```
**Isi yang aman:**
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
DB_PASSWORD=           # <-- KOSONG!
```

---

### 2. **`.gitignore`**
```
✅ WAJIB DI-PUSH
```
Agar developer lain juga terlindungi

---

### 3. **`composer.json` & `composer.lock`**
```
✅ WAJIB DI-PUSH
```
Agar dependencies konsisten

---

### 4. **`package.json` & `package-lock.json`**
```
✅ WAJIB DI-PUSH
```
Agar front-end dependencies konsisten

---

### 5. **Semua file code:**
```
✅ AMAN DI-PUSH
- /app/
- /config/
- /database/migrations/
- /routes/
- /resources/
- /public/ (kecuali /public/storage/)
```

**CATATAN:** Pastikan tidak ada password/API key hardcoded!

---

## 🔍 CARA CEK SEBELUM PUSH

### 1. **Cek file yang akan di-commit:**
```bash
git status
```

### 2. **Pastikan .env tidak ada:**
```bash
git ls-files | grep .env
```
Output harus kosong atau hanya `.env.example`

### 3. **Cek diff detail:**
```bash
git diff --staged
```

### 4. **Cek apakah ada credentials tercampur:**
```bash
# Cari pattern password/key di staged files
git diff --staged | grep -i "password\|secret\|key\|token"
```

---

## 🚨 JIKA SUDAH TERLANJUR DI-PUSH

### Langkah Darurat:

#### 1. **Langsung ganti semua credentials!**
```bash
# Generate APP_KEY baru
php artisan key:generate --force

# Ganti password database
# Ganti API keys
# Ganti email password
```

#### 2. **Hapus dari Git History (Advanced)**
```bash
# Gunakan BFG Repo-Cleaner
# Download dari: https://rtyley.github.io/bfg-repo-cleaner/

java -jar bfg.jar --delete-files .env
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push origin --force --all
```

⚠️ **WARNING:** Force push akan overwrite history!

#### 3. **Inform team segera!**

---

## ✅ CHECKLIST SEBELUM `git push`

```
□ .env tidak ada di git status
□ /vendor/ tidak ter-commit
□ /node_modules/ tidak ter-commit
□ *.log tidak ter-commit
□ *.sql tidak ter-commit
□ Tidak ada password hardcoded
□ Tidak ada API key hardcoded
□ .env.example sudah update (tanpa value)
□ .gitignore sudah proper
```

---

## 🛡️ TIPS KEAMANAN

### 1. **Selalu Review Sebelum Commit**
```bash
# JANGAN pakai git add .
# Lebih baik spesifik:
git add app/
git add database/migrations/
git add routes/
```

### 2. **Setup Pre-commit Hook**
Buat file `.git/hooks/pre-commit`:
```bash
#!/bin/sh
if git diff --cached --name-only | grep -q "^\.env$"; then
    echo "❌ ERROR: .env tidak boleh di-commit!"
    echo "Hapus dengan: git reset .env"
    exit 1
fi
```

### 3. **Enable GitHub Secret Scanning**
- Gratis untuk public repo
- Deteksi jika API key/token bocor

### 4. **Review Pull Request**
- Jangan langsung merge
- Cek apakah ada file sensitif

---

## 📞 KONTAK

Jika ada pertanyaan atau incident:
- Hubungi: Admin/Team Lead
- Email: [admin@spbe.local]

---

**Dokumen ini WAJIB dibaca oleh semua developer!**

Last Updated: 11 Februari 2026
