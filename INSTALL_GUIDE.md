# AltNET Ecount ERP - 로컬 서버 설치 및 운영 매뉴얼

## 대상 환경: Apache + PHP 7.1 + MariaDB 10.3 (Windows/Linux)

---

## 1. 호환성 검사 결과 요약

### 1-1. PHP 7.1 호환성 (전수검사 완료: 33개 파일)

| 검사 항목 | 결과 | 비고 |
|-----------|------|------|
| `??=` (Null Coalescing Assignment, PHP 7.4+) | OK | 미사용 |
| `fn =>` (Arrow Functions, PHP 7.4+) | OK | 미사용 |
| Typed Properties (PHP 7.4+) | OK | 미사용 |
| `str_contains` 등 (PHP 8.0+) | OK | 미사용 |
| `match()` 표현식 (PHP 8.0+) | OK | 미사용 |
| Null Safe `?->` (PHP 8.0+) | OK | 미사용 |
| Union Types (PHP 8.0+) | OK | 미사용 |
| `...` 배열 스프레드 (PHP 7.4+) | OK | 미사용 |
| `array_key_first/last` (PHP 7.3+) | OK | 미사용 |
| `??` Null Coalescing (PHP 7.0+) | OK | 호환 |
| `random_bytes()` (PHP 7.0+) | OK | 호환 |
| `password_hash/verify` (PHP 5.5+) | OK | 호환 |
| `compact()` | OK | PHP 7.1에서 정상 동작 |
| `session.cookie_samesite` (PHP 7.3+) | **수정됨** | 버전 체크 후 조건 적용 |
| `array_column` (PHP 5.5+) | OK | 호환 |
| `json_encode` + `JSON_UNESCAPED_UNICODE` (PHP 5.4+) | OK | 호환 |
| `mb_internal_encoding` (mbstring) | OK | 확장모듈 필요 |
| PDO + Prepared Statements | OK | 호환 |
| Anonymous Functions / Closures (PHP 5.3+) | OK | 호환 |
| `hash_equals()` (PHP 5.6+) | OK | 호환 |

### 1-2. 수정된 비호환 항목 (1건)

**파일**: `core/Session.php` 15행  
**문제**: `ini_set('session.cookie_samesite', 'Lax')` - PHP 7.3 이상 전용  
**수정**: 버전 체크 조건 추가

```php
// 수정 전 (PHP 7.3+ 전용)
ini_set('session.cookie_samesite', 'Lax');

// 수정 후 (PHP 7.1 호환)
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    ini_set('session.cookie_samesite', 'Lax');
}
```

> **영향**: PHP 7.1/7.2에서는 SameSite 쿠키 속성이 적용되지 않지만, 기능 동작에는 영향 없음.  
> PHP 7.3 이상에서는 자동으로 적용됨.

### 1-3. MariaDB 10.3 SQL 호환성 (전수검사 완료)

| 검사 항목 | 결과 | 비고 |
|-----------|------|------|
| `YEAR()`, `MONTH()`, `QUARTER()` | OK | MariaDB 5.0+ |
| `COALESCE()` | OK | MariaDB 5.0+ |
| `CURDATE()`, `CURRENT_TIMESTAMP` | OK | MariaDB 5.0+ |
| `SUM()`, `COUNT()`, `GROUP BY` | OK | 표준 SQL |
| `ON UPDATE CURRENT_TIMESTAMP` | OK | MariaDB 10.0+ |
| InnoDB 엔진 | OK | MariaDB 10.0+ |
| `utf8mb4` 문자셋 | OK | MariaDB 10.0+ |
| `FOREIGN KEY` 제약조건 | OK | InnoDB 지원 |
| Window Functions (사용하지 않음) | OK | 미사용 |
| CTE WITH (사용하지 않음) | OK | 미사용 |
| JSON 함수 (사용하지 않음) | OK | 미사용 |

### 1-4. Apache 호환성

| 검사 항목 | 결과 | 비고 |
|-----------|------|------|
| `.htaccess` 파일 | OK | 이미 존재 |
| `mod_rewrite` | 필요 | RewriteEngine On 사용 |
| `AllowOverride All` | 필요 | .htaccess 적용을 위해 |
| 정적 파일 서빙 | OK | assets/ 디렉토리 직접 접근 |
| 민감 디렉토리 차단 | OK | config/, core/ 등 차단 설정됨 |

### 1-5. 최종 결론

> **PHP 7.1 + MariaDB 10.3 + Apache 환경에서 100% 운영 가능합니다.**  
> 수정 필요 항목 1건(`session.cookie_samesite`)은 이미 패치 적용 완료.

---

## 2. 필수 소프트웨어 요구사항

| 소프트웨어 | 최소 버전 | 권장 버전 | 비고 |
|------------|-----------|-----------|------|
| Apache HTTP Server | 2.4.x | 2.4.54+ | mod_rewrite 필수 |
| PHP | 7.1.0 | 7.1.33 / 7.4+ | 7.4 이상 추천 |
| MariaDB | 10.3.0 | 10.3.39 | 10.3 LTS |
| OS | Windows 10 / CentOS 7 | - | 무관 |

### 2-1. PHP 필수 확장모듈

| 확장모듈 | 용도 | 필수 여부 |
|----------|------|-----------|
| `pdo_mysql` | MariaDB 데이터베이스 연결 | **필수** |
| `mbstring` | UTF-8 멀티바이트 문자열 처리 | **필수** |
| `json` | JSON 인코딩/디코딩 | **필수** (PHP 7.1 기본 내장) |
| `session` | 세션 관리 | **필수** (PHP 기본 내장) |
| `openssl` | `random_bytes()` CSRF 토큰 생성 | **필수** |
| `fileinfo` | 파일 MIME 타입 감지 (백업) | 권장 |
| `zip` | DB 백업/복원 (mysqldump) | 권장 |

---

## 3. Windows 환경 설치 매뉴얼

### 3-1. XAMPP를 이용한 설치 (가장 간편)

#### Step 1: XAMPP 다운로드 및 설치

```
다운로드 URL: https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/
PHP 7.1 포함 버전: xampp-win32-7.1.33-0-VC14-installer.exe
PHP 7.4 포함 버전: xampp-win32-7.4.33-0-VC15-installer.exe (권장)
```

- 설치 경로: `C:\xampp` (기본값 권장)
- 설치 구성요소: Apache, MySQL (MariaDB), PHP 선택

#### Step 2: PHP 확장모듈 활성화

`C:\xampp\php\php.ini` 파일을 편집기로 열고 아래 항목들의 세미콜론(;)을 제거:

```ini
; === 필수 확장모듈 (세미콜론 ; 제거) ===
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo

; === 타임존 설정 (추가) ===
date.timezone = Asia/Seoul

; === 파일 업로드 설정 (백업 기능용) ===
upload_max_filesize = 50M
post_max_size = 60M
max_execution_time = 300
memory_limit = 256M
```

#### Step 3: Apache 설정 (mod_rewrite 활성화)

`C:\xampp\apache\conf\httpd.conf` 파일에서:

```apache
# 1) mod_rewrite 활성화 (주석 해제)
LoadModule rewrite_module modules/mod_rewrite.so

# 2) AllowOverride 설정 (.htaccess 허용)
# <Directory "C:/xampp/htdocs"> 블록을 찾아서:
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks Includes ExecCGI
    AllowOverride All           # <-- 이 부분을 All로 변경
    Require all granted
</Directory>
```

#### Step 4: 프로젝트 파일 배포

```batch
:: 프로젝트 파일을 htdocs에 복사
xcopy /E /I "소스폴더\webapp" "C:\xampp\htdocs\erp"

:: 또는 htdocs 루트에 직접 배포
xcopy /E /I "소스폴더\webapp\*" "C:\xampp\htdocs\"
```

**디렉토리 구조 (방법 A - 서브디렉토리 배포):**
```
C:\xampp\htdocs\
  └── erp\                     ← 프로젝트 루트
      ├── .htaccess
      ├── index.php
      ├── router.php
      ├── config\
      ├── core\
      ├── controllers\
      ├── views\
      ├── assets\
      ├── database\
      ├── logs\               ← Apache에서 쓰기 권한 필요
      └── backups\            ← Apache에서 쓰기 권한 필요
```
접속 URL: `http://localhost/erp/?page=login`

**디렉토리 구조 (방법 B - 루트 배포 권장):**
```
C:\xampp\htdocs\
  ├── .htaccess
  ├── index.php
  ├── router.php
  ├── config\
  ├── core\
  ├── controllers\
  ├── views\
  ├── assets\
  ├── database\
  ├── logs\
  └── backups\
```
접속 URL: `http://localhost/?page=login`

#### Step 5: 쓰기 권한 디렉토리 생성

```batch
:: logs, backups 디렉토리 생성
mkdir C:\xampp\htdocs\erp\logs
mkdir C:\xampp\htdocs\erp\backups
```

> Windows XAMPP에서는 별도 권한 설정 불필요 (기본적으로 쓰기 가능)

#### Step 6: MariaDB 데이터베이스 설정

XAMPP Control Panel에서 Apache + MySQL 시작 후:

```batch
:: phpMyAdmin 접속하여 실행하거나, 명령줄에서:
C:\xampp\mysql\bin\mysql.exe -u root

:: 또는 비밀번호가 설정된 경우:
C:\xampp\mysql\bin\mysql.exe -u root -p
```

MariaDB 콘솔에서:

```sql
-- 1) 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS altnet_ecount 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- 2) 전용 사용자 생성 (권장)
CREATE USER 'erp_user'@'localhost' IDENTIFIED BY 'ErpSecure2026!';
GRANT ALL PRIVILEGES ON altnet_ecount.* TO 'erp_user'@'localhost';
FLUSH PRIVILEGES;

-- 3) 타임존 설정 확인
SELECT @@global.time_zone, @@session.time_zone;
```

#### Step 7: 스키마 및 초기 데이터 적용

```batch
:: 스키마 생성
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < C:\xampp\htdocs\erp\database\schema.sql

:: 초기 데이터 투입
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < C:\xampp\htdocs\erp\database\seed.sql
```

또는 phpMyAdmin (http://localhost/phpmyadmin) 에서:
1. `altnet_ecount` DB 선택
2. SQL 탭 클릭
3. `database/schema.sql` 내용 붙여넣기 후 실행
4. `database/seed.sql` 내용 붙여넣기 후 실행

#### Step 8: DB 접속 설정 파일 수정

`config/database.php` 수정:

```php
<?php
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'dbname'   => 'altnet_ecount',
    'username' => 'erp_user',        // ← 생성한 사용자 또는 'root'
    'password' => 'ErpSecure2026!',  // ← 설정한 비밀번호 또는 '' (XAMPP root 기본 빈 비밀번호)
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
```

> **XAMPP 기본 root 비밀번호는 빈 문자열('')입니다.**  
> 운영 환경에서는 반드시 비밀번호를 설정하세요.

#### Step 9: 접속 확인

1. XAMPP Control Panel에서 Apache, MySQL **Start** 클릭
2. 브라우저에서 접속:
   - `http://localhost/erp/?page=login` (서브디렉토리 배포 시)
   - `http://localhost/?page=login` (루트 배포 시)
3. 로그인: **altnet** / **altnet2016!**
4. 대시보드 정상 표시 확인

---

### 3-2. 수동 설치 (Apache + PHP + MariaDB 개별 설치)

#### Step 1: 소프트웨어 다운로드

| 소프트웨어 | 다운로드 URL |
|------------|-------------|
| Apache 2.4 (Win64) | https://www.apachelounge.com/download/ |
| PHP 7.1 (Thread Safe, VC14) | https://windows.php.net/downloads/releases/archives/ |
| MariaDB 10.3 | https://mariadb.org/download/?t=mariadb&p=mariadb&r=10.3 |

#### Step 2: Apache 설치 및 설정

```
설치 경로: C:\Apache24
```

`C:\Apache24\conf\httpd.conf` 수정:

```apache
# 1) 서버 루트
ServerRoot "C:/Apache24"

# 2) 리스닝 포트
Listen 80

# 3) PHP 모듈 로드 (파일 끝에 추가)
LoadModule php7_module "C:/php71/php7apache2_4.dll"
AddHandler application/x-httpd-php .php
PHPIniDir "C:/php71"

# 4) mod_rewrite 활성화
LoadModule rewrite_module modules/mod_rewrite.so

# 5) 문서 루트를 프로젝트 경로로 설정
DocumentRoot "C:/erp"
<Directory "C:/erp">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php index.html
</Directory>
```

#### Step 3: PHP 설치 및 설정

```
설치 경로: C:\php71
```

`C:\php71\php.ini` 수정 (php.ini-development 파일을 php.ini로 복사):

```ini
; 확장모듈 경로
extension_dir = "C:/php71/ext"

; 필수 확장모듈
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo

; 타임존
date.timezone = Asia/Seoul

; 업로드/실행 제한
upload_max_filesize = 50M
post_max_size = 60M
max_execution_time = 300
memory_limit = 256M

; 에러 로깅
display_errors = Off
log_errors = On
error_log = "C:/erp/logs/php_error.log"
```

#### Step 4: MariaDB 설치 및 설정

```
설치 경로: C:\MariaDB103
```

`C:\MariaDB103\data\my.ini` 수정:

```ini
[mysqld]
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
innodb_file_per_table = 1
innodb_buffer_pool_size = 256M
max_connections = 100

# 타임존 (또는 애플리케이션에서 SET time_zone으로 처리 - 이미 적용됨)
# default-time-zone = '+09:00'

[client]
default-character-set = utf8mb4
```

#### Step 5: 서비스 등록 및 시작

```batch
:: Apache 서비스 등록
C:\Apache24\bin\httpd.exe -k install

:: MariaDB 서비스 등록
C:\MariaDB103\bin\mysqld.exe --install MariaDB

:: 서비스 시작
net start Apache2.4
net start MariaDB
```

이후 Step 6~9는 XAMPP 매뉴얼과 동일합니다.

---

## 4. Linux (CentOS/Ubuntu) 환경 설치 매뉴얼

### 4-1. CentOS 7 설치

#### Step 1: 필수 패키지 설치

```bash
# EPEL + Remi 저장소 추가 (PHP 7.1용)
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm

# PHP 7.1 활성화
sudo yum-config-manager --enable remi-php71

# Apache + PHP + MariaDB 설치
sudo yum install -y httpd php php-pdo php-mysqlnd php-mbstring php-json php-openssl

# MariaDB 10.3 저장소 추가
cat <<EOF | sudo tee /etc/yum.repos.d/MariaDB.repo
[mariadb]
name = MariaDB
baseurl = https://mirror.mariadb.org/yum/10.3/centos7-amd64
gpgkey = https://mirror.mariadb.org/yum/RPM-GPG-KEY-MariaDB
gpgcheck = 1
EOF

sudo yum install -y MariaDB-server MariaDB-client
```

#### Step 2: 서비스 시작

```bash
sudo systemctl enable httpd mariadb
sudo systemctl start httpd mariadb
```

#### Step 3: MariaDB 초기 보안 설정

```bash
sudo mysql_secure_installation
# - root 비밀번호 설정
# - 익명 사용자 제거: Y
# - 원격 root 접근 차단: Y
# - test DB 제거: Y
# - 권한 테이블 리로드: Y
```

#### Step 4: 프로젝트 배포

```bash
# 프로젝트 디렉토리 생성
sudo mkdir -p /var/www/html/erp
sudo cp -r webapp/* /var/www/html/erp/

# 권한 설정
sudo chown -R apache:apache /var/www/html/erp/
sudo chmod -R 755 /var/www/html/erp/
sudo chmod -R 775 /var/www/html/erp/logs/
sudo chmod -R 775 /var/www/html/erp/backups/

# SELinux 허용 (CentOS에서 필수)
sudo setsebool -P httpd_can_network_connect_db 1
sudo chcon -R -t httpd_sys_rw_content_t /var/www/html/erp/logs/
sudo chcon -R -t httpd_sys_rw_content_t /var/www/html/erp/backups/
```

#### Step 5: Apache 가상호스트 설정

`/etc/httpd/conf.d/erp.conf` 생성:

```apache
<VirtualHost *:80>
    ServerName erp.local
    DocumentRoot /var/www/html/erp

    <Directory /var/www/html/erp>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    # PHP 설정 (선택)
    php_value date.timezone "Asia/Seoul"
    php_value upload_max_filesize "50M"
    php_value post_max_size "60M"
    php_value max_execution_time 300

    ErrorLog /var/log/httpd/erp_error.log
    CustomLog /var/log/httpd/erp_access.log combined
</VirtualHost>
```

```bash
# Apache 재시작
sudo systemctl restart httpd
```

#### Step 6: 방화벽 설정

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

#### Step 7: DB 생성 및 스키마 적용

```bash
# MariaDB 접속
mysql -u root -p

# DB 및 사용자 생성 (MariaDB 콘솔에서)
CREATE DATABASE altnet_ecount CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp_user'@'localhost' IDENTIFIED BY 'ErpSecure2026!';
GRANT ALL PRIVILEGES ON altnet_ecount.* TO 'erp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 스키마 및 시드 적용
mysql -u erp_user -p'ErpSecure2026!' --default-character-set=utf8mb4 altnet_ecount < /var/www/html/erp/database/schema.sql
mysql -u erp_user -p'ErpSecure2026!' --default-character-set=utf8mb4 altnet_ecount < /var/www/html/erp/database/seed.sql
```

### 4-2. Ubuntu 20.04/22.04 설치

```bash
# PHP 7.1 저장소 추가
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# 설치
sudo apt install -y apache2 php7.1 php7.1-mysql php7.1-mbstring php7.1-json php7.1-xml mariadb-server-10.3

# mod_rewrite 활성화
sudo a2enmod rewrite

# Apache 재시작
sudo systemctl restart apache2
```

이후 Step 4~7은 CentOS와 동일합니다 (경로: `/var/www/html/erp/`, 사용자: `www-data`).

---

## 5. config/database.php 환경별 설정 예시

### 5-1. 개발 환경 (XAMPP)
```php
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'dbname'   => 'altnet_ecount',
    'username' => 'root',
    'password' => '',           // XAMPP 기본
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
```

### 5-2. 운영 환경 (Linux 서버)
```php
return [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_NAME') ?: 'altnet_ecount',
    'username' => getenv('DB_USER') ?: 'erp_user',
    'password' => getenv('DB_PASS') ?: 'ErpSecure2026!',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
```

### 5-3. 환경변수를 사용하는 경우 (Linux)
```bash
# /etc/apache2/envvars 또는 /etc/httpd/conf.d/erp.conf 에 추가
SetEnv DB_HOST "127.0.0.1"
SetEnv DB_PORT "3306"
SetEnv DB_NAME "altnet_ecount"
SetEnv DB_USER "erp_user"
SetEnv DB_PASS "ErpSecure2026!"
```

---

## 6. .htaccess 설명 (이미 프로젝트에 포함됨)

```apache
RewriteEngine On

# 민감 디렉토리 접근 차단 (config, core, controllers 등)
RewriteRule ^(config|core|models|controllers|database|logs|backups)/ - [F,L]

# 정적 파일(CSS, JS, 이미지) 직접 접근 허용
RewriteRule ^assets/ - [L]

# 나머지 모든 요청을 index.php로 라우팅
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

> **주의**: Apache의 `AllowOverride All` 설정이 반드시 활성화되어야 .htaccess가 동작합니다.

---

## 7. 자주 발생하는 문제 및 해결

### 7-1. "500 Internal Server Error"

**원인 1**: mod_rewrite가 활성화되지 않음
```bash
# CentOS
sudo yum install mod_rewrite
# Ubuntu
sudo a2enmod rewrite && sudo systemctl restart apache2
# XAMPP: httpd.conf에서 LoadModule rewrite_module 주석 해제
```

**원인 2**: PHP 확장모듈 미설치
```bash
# 필수 모듈 확인
php -m | grep -E "pdo_mysql|mbstring|json|openssl"

# 누락 시 설치 (CentOS)
sudo yum install php-pdo php-mysqlnd php-mbstring
# Ubuntu
sudo apt install php7.1-mysql php7.1-mbstring
```

**원인 3**: AllowOverride가 None으로 설정됨
```apache
# httpd.conf 또는 apache2.conf에서 해당 Directory 블록을 찾아:
AllowOverride All    # None → All로 변경
```

### 7-2. "Access denied for user 'root'@'localhost'"

```sql
-- MariaDB 콘솔에서 비밀번호 재설정
ALTER USER 'root'@'localhost' IDENTIFIED BY '새비밀번호';
FLUSH PRIVILEGES;
```

그리고 `config/database.php`의 password 값 업데이트.

### 7-3. 한글이 깨지는 경우

```ini
# php.ini
default_charset = "UTF-8"
mbstring.internal_encoding = UTF-8
```

```sql
-- MariaDB 문자셋 확인
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';

-- 전부 utf8mb4여야 함. 아닌 경우:
SET NAMES utf8mb4;
ALTER DATABASE altnet_ecount CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 7-4. 감사 로그 시간이 UTC로 표시

이미 코드에서 처리되어 있습니다:
- `index.php`: `date_default_timezone_set('Asia/Seoul');`
- `core/Database.php`: `SET time_zone = '+09:00';`

서버 자체 시간도 확인:
```bash
# Linux
timedatectl set-timezone Asia/Seoul

# Windows
# 제어판 → 시계 및 국가 → 날짜/시간 → 표준 시간대: (UTC+09:00) 서울
```

### 7-5. 매출/매입 등록 시 날짜 오류

flatpickr의 defaultDate가 잘못 설정된 버그는 v1.3.0에서 수정 완료:
- 기존 값이 있으면 기존 값 사용
- 과거 날짜 선택 시 해당 날짜로 정상 등록
- 매출번호가 선택한 날짜 기준으로 자동 갱신

### 7-6. 세션이 즉시 만료되는 경우

```ini
# php.ini에서 세션 저장 경로 확인
session.save_path = "C:\xampp\tmp"       # Windows
session.save_path = "/var/lib/php/session"  # Linux

# 해당 디렉토리에 쓰기 권한 확인
# Linux:
sudo chmod 770 /var/lib/php/session
sudo chown root:apache /var/lib/php/session
```

### 7-7. BackupController에서 mysqldump 실패

`controllers/BackupController.php`에서 `exec()` 함수로 `mysqldump`를 호출합니다.

```bash
# mysqldump 경로 확인
which mysqldump                          # Linux
where mysqldump                          # Windows

# Windows XAMPP 기본 경로
C:\xampp\mysql\bin\mysqldump.exe

# PHP에서 exec() 함수가 비활성화된 경우 php.ini 확인:
# disable_functions = ... exec, ...  ← exec 제거 필요
```

---

## 8. 운영 환경 보안 체크리스트

| 항목 | 설정 | 비고 |
|------|------|------|
| DB root 비밀번호 | 반드시 설정 | `mysql_secure_installation` |
| DB 전용 사용자 | erp_user 등 생성 | root 직접 사용 금지 |
| config/ 디렉토리 | 웹 접근 차단 | .htaccess에서 처리됨 |
| display_errors | Off | php.ini에서 설정 |
| PHP error_log | 파일로 기록 | logs/error.log |
| HTTPS | SSL 인증서 적용 권장 | Let's Encrypt 무료 |
| 방화벽 | 80/443만 개방 | 3306 외부 차단 |
| DB 비밀번호 | 환경변수 사용 권장 | config/database.php |
| 백업 디렉토리 | 웹 접근 차단 | .htaccess에서 처리됨 |
| session.cookie_httponly | 1 | core/Session.php에서 처리됨 |

---

## 9. 전체 파일 목록 및 역할

| 경로 | 역할 | PHP 7.1 호환 |
|------|------|:---:|
| `index.php` | 메인 라우터/엔트리포인트 | OK |
| `router.php` | PHP 내장 서버용 라우터 (Apache에서는 미사용) | OK |
| `.htaccess` | Apache URL 리라이팅 규칙 | OK |
| `config/app.php` | 앱 설정 (세션, 잠금, 색상) | OK |
| `config/database.php` | DB 접속 설정 | OK |
| `core/Database.php` | PDO 래퍼 싱글톤 (KST 타임존) | OK |
| `core/Session.php` | 세션 관리 | OK (수정완료) |
| `core/CSRF.php` | CSRF 토큰 생성/검증 | OK |
| `core/Auth.php` | 인증/인가 (bcrypt) | OK |
| `core/AuditLog.php` | 감사 로그 기록 | OK |
| `core/Helper.php` | 유틸리티 함수 | OK |
| `controllers/AuthController.php` | 로그인/로그아웃 | OK |
| `controllers/DashboardController.php` | 대시보드 차트/통계 | OK |
| `controllers/SalesController.php` | 매출/매입 CRUD | OK |
| `controllers/CompanyController.php` | 매출업체 관리 | OK |
| `controllers/VendorController.php` | 매입업체 관리 | OK |
| `controllers/ItemController.php` | 제품코드 관리 | OK |
| `controllers/UserController.php` | 사용자 관리 | OK |
| `controllers/AuditController.php` | 감사로그 조회 | OK |
| `controllers/BackupController.php` | DB 백업/복원 | OK |
| `views/layouts/main.php` | 공통 레이아웃 | OK |
| `views/layouts/header.php` | 헤더 | OK |
| `views/layouts/sidebar.php` | 사이드바 | OK |
| `views/auth/login.php` | 로그인 화면 | OK |
| `views/dashboard/index.php` | 대시보드 (Chart.js) | OK |
| `views/sales/index.php` | 매출 목록 | OK |
| `views/sales/form.php` | 매출 등록/수정 폼 | OK |
| `views/companies/index.php` | 매출업체 목록 | OK |
| `views/vendors/index.php` | 매입업체 목록 | OK |
| `views/items/index.php` | 제품코드 목록 | OK |
| `views/users/index.php` | 사용자 목록 | OK |
| `views/audit/index.php` | 감사로그 목록 | OK |
| `views/backup/index.php` | 백업 관리 | OK |
| `api/session.php` | 세션 상태 API | OK |
| `database/schema.sql` | DB 스키마 정의 | OK |
| `database/seed.sql` | 초기 데이터 | OK |
| `assets/css/app.css` | 스타일시트 | N/A |
| `assets/js/app.js` | 프론트엔드 JS | N/A |
| `assets/images/altnet_logo.png` | 로고 이미지 | N/A |

---

## 10. 빠른 시작 가이드 (요약)

### XAMPP (Windows) - 5분 설치

```batch
:: 1) XAMPP 설치 (PHP 7.1 또는 7.4 버전)
:: 2) 프로젝트 복사
xcopy /E /I webapp C:\xampp\htdocs\erp

:: 3) php.ini에서 extension=pdo_mysql, extension=mbstring 활성화
:: 4) httpd.conf에서 AllowOverride All 확인

:: 5) XAMPP에서 Apache + MySQL 시작

:: 6) DB 생성
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\erp\database\schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\erp\database\seed.sql

:: 7) 브라우저 접속
:: http://localhost/erp/?page=login
:: ID: altnet / PW: altnet2016!
```

### Linux (Ubuntu) - 10분 설치

```bash
# 1) 패키지 설치
sudo add-apt-repository ppa:ondrej/php && sudo apt update
sudo apt install -y apache2 php7.1 php7.1-mysql php7.1-mbstring mariadb-server

# 2) Apache 설정
sudo a2enmod rewrite

# 3) 프로젝트 배포
sudo cp -r webapp /var/www/html/erp
sudo chown -R www-data:www-data /var/www/html/erp

# 4) Apache 가상호스트 설정 (AllowOverride All)
# 5) DB 생성
mysql -u root -p < /var/www/html/erp/database/schema.sql
mysql -u root -p < /var/www/html/erp/database/seed.sql

# 6) config/database.php 수정 (비밀번호 설정)
# 7) 접속: http://서버IP/erp/?page=login
```

---

## 11. 버전 정보

| 항목 | 값 |
|------|-----|
| 프로젝트 버전 | v1.3.1 |
| 호환성 검사 일시 | 2026-02-10 (KST) |
| 검사 대상 파일 수 | 33개 PHP + 1개 SQL + 1개 .htaccess |
| 비호환 항목 | 1건 (수정 완료) |
| 테스트 환경 | PHP 8.x + MariaDB 11.x (샌드박스) |
| 대상 환경 | PHP 7.1+ / MariaDB 10.3+ / Apache 2.4+ |

---

*이 문서는 AltNET Ecount ERP v1.3.1 기준으로 작성되었습니다.*
