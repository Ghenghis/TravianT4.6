# Security Audit Report

**Date:** 2025-10-30 18:21:45
**Scope:** SQL Injection + XSS + Dependencies + Infrastructure

## Executive Summary

Security audit covering:
- SQL Injection vulnerabilities
- XSS vulnerabilities  
- PHP dependency vulnerabilities
- Docker image vulnerabilities
- Dockerfile best practices

---


## 1. SQL Injection (SQLi) Audit

### Summary

- String concatenation patterns: **32**
- SELECT with embedded variables: **10**
- UPDATE with embedded variables: **3**
- INSERT with embedded variables: **0**
- DELETE with embedded variables: **1**
- sprintf/printf SQL building: **0**
- Legacy mysqli usage: **0**
- Prepared statements (safe): **339**

### Detailed Findings

```
[1;33m🔍 SQL Injection Security Audit[0m

Pattern 1: String concatenation in SQL queries
Searching for: query/execute calls with concatenated variables...
[0;31m⚠️  Found potential SQL concatenation:[0m
sections/api/include/Api/Ctrl/NewsCtrl.php:41:        $news = $db->query("SELECT * FROM news WHERE expire > " . time() . " ORDER BY time DESC LIMIT 3");
sections/api/include/Api/Ctrl/ServersCtrl.php:122:        $stmt = $db->query("SELECT * FROM gameServers");
sections/api/include/Api/Ctrl/AuthCtrl.php:51:            $db->query("DELETE FROM passwordRecovery WHERE id={$recovery['id']}");
sections/api/include/Api/Ctrl/AuthCtrl.php:85:        $serverFindStatement = $db->query("SELECT worldId, gameWorldUrl, configFileLocation FROM gameServers WHERE finished=0 ORDER BY startTime DESC");
sections/api/include/Api/Ctrl/VillageCtrl.php:208:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'building')");
sections/api/include/Api/Ctrl/MapCtrl.php:200:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'odata')");
sections/api/include/Api/Ctrl/TroopCtrl.php:47:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'movement')");
sections/api/include/Api/Ctrl/TroopCtrl.php:200:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'training')");
sections/api/include/Api/Ctrl/TroopCtrl.php:406:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'movement')");
sections/api/include/Api/Ctrl/AllianceCtrl.php:296:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'diplomacy')");
sections/api/include/Api/Ctrl/MarketCtrl.php:33:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
sections/api/include/Api/Ctrl/MarketCtrl.php:304:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
sections/api/include/Api/Ctrl/MarketCtrl.php:382:        $tableCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'market')");
sections/api/include/Api/Ctrl/QuestCtrl.php:147:            $heroCheck = $serverDB->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'hero_profile')");
sections/api/include/Api/Ctrl/StatisticsCtrl.php:379:        $playersStmt = $serverDB->query("SELECT COUNT(*) FROM users WHERE access >= 1");
sections/api/include/Api/Ctrl/StatisticsCtrl.php:382:        $villagesStmt = $serverDB->query("SELECT COUNT(*) as count, SUM(pop) as population FROM vdata WHERE owner > 0");
sections/api/include/Api/Ctrl/StatisticsCtrl.php:385:        $alliancesStmt = $serverDB->query("SELECT COUNT(*) FROM alliance");
sections/api/include/Api/Ctrl/ReportsCtrl.php:73:        $countSql = "SELECT COUNT(*) as total FROM (
sections/api/include/Api/Ctrl/MessagesCtrl.php:84:        $countQuery = str_replace("SELECT m.*, sender.name as sender_name, recipient.name as recipient_name", "SELECT COUNT(*)", $query);
sections/api/include/Api/Ctrl/RegisterCtrl.php:94:            $db->query("UPDATE activation SET used=1 WHERE id=" . $activation['id']);
[1;33mTotal: 32 instances[0m

Pattern 2: SELECT queries with embedded variables
[0;31m⚠️  Found SELECT with embedded variables:[0m
sections/api/include/Api/Ctrl/StatisticsCtrl.php:337:                SELECT u.id, u.name, {$column} as value
sections/api/include/Api/Ctrl/MessagesCtrl.php:84:        $countQuery = str_replace("SELECT m.*, sender.name as sender_name, recipient.name as recipient_name", "SELECT COUNT(*)", $query);
sections/api/include/Api/Controllers/MonitoringCtrl.php:129:                SELECT COUNT(*) as total FROM decision_log {$whereClause}
sections/api/include/Security/DatabaseSecurity.php:11:        if (!preg_match('/^\s*SELECT/i', $query)) {
sections/api/vendor/predis/predis/src/Command/Argument/TimeSeries/CommonArguments.php:150:        array_push($this->arguments, 'SELECTED_LABELS', ...$labels);
sections/api/vendor/predis/predis/src/Connection/Factory.php:196:                new RawCommand('SELECT', [$parameters->database])
sections/api/vendor/predis/predis/src/Connection/RelayFactory.php:145:                new RawCommand('SELECT', [$parameters->database])
sections/api/test-npc-visibility.php:160:            SELECT kid FROM vdata WHERE owner IN ($placeholders)
sections/api/test-npc-visibility.php:183:            SELECT id FROM users WHERE id IN ($placeholders)
sections/api/test-npc-visibility.php:185:            SELECT id FROM users WHERE id IN ($placeholders)
[1;33mTotal: 10 instances[0m

Pattern 3: UPDATE queries with embedded variables
[0;31m⚠️  Found UPDATE with embedded variables:[0m
sections/api/include/Api/Ctrl/RegisterCtrl.php:94:            $db->query("UPDATE activation SET used=1 WHERE id=" . $activation['id']);
sections/api/include/Api/Controllers/SpawnPresetCtrl.php:184:            $sql = "UPDATE spawn_presets SET " . implode(', ', $updates) . " WHERE id = ?";
sections/api/include/Api/Controllers/NPCManagementCtrl.php:238:            $sql = "UPDATE ai_configs SET " . implode(', ', $updates) . " WHERE npc_player_id = ?";
[1;33mTotal: 3 instances[0m

Pattern 4: INSERT queries with embedded variables
[0;32m✅ No INSERT with embedded vars[0m
[1;33mTotal: 0 instances[0m

Pattern 5: DELETE queries with embedded variables
[0;31m⚠️  Found DELETE with embedded variables:[0m
sections/api/include/Api/Ctrl/AuthCtrl.php:51:            $db->query("DELETE FROM passwordRecovery WHERE id={$recovery['id']}");
[1;33mTotal: 1 instances[0m

Pattern 6: sprintf/printf SQL string building
[0;32m✅ No sprintf SQL building[0m
[1;33mTotal: 0 instances[0m

Pattern 7: Legacy mysqli usage
[0;32m✅ No legacy mysqli usage[0m
[1;33mTotal: 0 instances[0m

Pattern 8: PDO prepared statement usage (safe pattern)
[0;32m✅ Found 339 prepared statement calls in 44 files[0m

[1;33m=== Summary ===[0m
[0;31m⚠️  Total potential issues: 46[0m

Breakdown:
  - Concatenation: 32
  - SELECT with vars: 10
  - UPDATE with vars: 3
  - INSERT with vars: 0
  - DELETE with vars: 1
  - sprintf building: 0
  - Legacy mysqli: 0

[0;32mSafe patterns: 339 prepared statement calls in 44 files[0m

Evidence files generated:
  - /tmp/sql-concat.txt (32 issues)
  - /tmp/sql-select.txt (10 issues)
  - /tmp/sql-update.txt (3 issues)
  - /tmp/sql-insert.txt (0 issues)
  - /tmp/sql-delete.txt (1 issues)
  - /tmp/sql-sprintf.txt (0 issues)
  - /tmp/sql-mysqli.txt (0 issues)
```

### Recommendations
1. Review all flagged patterns above
2. Convert to PDO prepared statements using DatabaseSecurity helper
3. Never concatenate user input into SQL strings
4. Add unit tests for all database operations

### Status
- [ ] All flagged patterns reviewed
- [ ] Conversions to prepared statements complete
- [ ] Unit tests added

---


## 2. Cross-Site Scripting (XSS) Audit

### Summary

- json_encode usages: **34**
- OutputEncoder helper: Available

### Detailed Findings

```
[1;33m🔍 XSS Security Audit[0m

Pattern 1: Direct echo of $_GET/$_POST/$_REQUEST
[0;32m✅ No direct echo found[0m

Pattern 2: Output without encoding
[1;33m⚠️  Found output without encoding (review needed):[0m
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:19:        echo "<!DOCTYPE html>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:98:        echo "\"><!--[if mso | IE]>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:158:        echo "</span>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:176:        echo "</span>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:182:        echo "</span>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:188:        echo "</span>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:194:        echo "</span>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:215:        echo "</a></td>
sections/api/include/Templates/Cache/03/03ed3a22b39eda7832b27764471e06f7ce1f2f607e2a78f1a13a9570353eb6e2.php:228:        echo "&#xA0;</span>
sections/api/include/vendor/google/recaptcha/examples/example-captcha.php:103:                echo '<kbd>' , $code , '</kbd> ';
sections/api/include/vendor/google/recaptcha/examples/example-captcha.php:121:            <div class="g-recaptcha" data-sitekey="<?php echo $siteKey; ?>"></div>
sections/api/include/vendor/phpmailer/phpmailer/src/POP3.php:386:            echo '<pre>';
sections/api/include/vendor/phpmailer/phpmailer/src/POP3.php:390:            echo '</pre>';
sections/api/include/vendor/twig/twig/lib/Twig/Node/Spaceless.php:32:            ->write("echo trim(preg_replace('/>\s+</', '><', ob_get_clean()));\n")
sections/api/include/vendor/twig/twig/test/Twig/Tests/Node/SpacelessTest.php:31:echo "<div>   <div>   foo   </div>   </div>";
sections/api/include/vendor/twig/twig/test/Twig/Tests/Node/SpacelessTest.php:32:echo trim(preg_replace('/>\s+</', '><', ob_get_clean()));

Pattern 3: JSON responses
[0;32m✅ Found 34 json_encode usages[0m

[0;32m✅ XSS audit complete[0m
```

### Recommendations
1. Use OutputEncoder::html() for all user-generated content in HTML
2. Use ValidationMiddleware for input sanitization
3. Use OutputEncoder::json() for API responses
4. Add functional XSS tests

### Status
- [ ] All user outputs use OutputEncoder
- [ ] All user inputs use ValidationMiddleware
- [ ] XSS tests added

---


## 3. Dependency Vulnerabilities

### Detailed Findings

```
[1;33m🔍 Dependency Vulnerability Scanning[0m

Scanning PHP dependencies (composer audit)...
[0;31m⚠️  Vulnerabilities found in PHP dependencies[0m
{
    "advisories": [],
    "abandoned": []
}

Scanning Docker images with Trivy...
[1;33m⚠️  Trivy not installed. Skipping Docker image scanning.[0m
[1;33mTo install Trivy: https://aquasecurity.github.io/trivy/latest/getting-started/installation/[0m

[0;32m✅ Dependency scanning complete[0m

Reports generated:
  - /tmp/composer-audit.json
  - /tmp/trivy-php.json (if Trivy installed)
  - /tmp/trivy-nginx.json (if Trivy installed)
```

### Recommendations
1. Update vulnerable PHP packages: `composer update`
2. Rebuild Docker images with patches
3. Monitor CVE feeds for new vulnerabilities

### Status
- [ ] Vulnerable packages updated
- [ ] Docker images rebuilt
- [ ] No HIGH/CRITICAL CVEs remaining

---


## 4. Dockerfile Best Practices

### Detailed Findings

```
[1;33m🔍 Dockerfile Security Scanning[0m

[1;33m⚠️  hadolint not installed. Skipping Dockerfile scanning.[0m
[1;33mTo install hadolint: https://github.com/hadolint/hadolint[0m
```

### Status
- [ ] Dockerfile warnings addressed

---

## 5. Security Controls Status

### DatabaseSecurity Helper
- ✅ Created: sections/api/include/Security/DatabaseSecurity.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

### ValidationMiddleware
- ✅ Created: sections/api/include/Middleware/ValidationMiddleware.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

### OutputEncoder
- ✅ Created: sections/api/include/Security/OutputEncoder.php
- ⚠️ **NOT YET INTEGRATED** - See docs/SECURITY-HELPERS-INTEGRATION.md

---

## Next Actions

1. **Review flagged SQL patterns** - CONCAT=32, SELECT=10, UPDATE=3, INSERT=0, DELETE=1, sprintf=0, mysqli=0
2. **Integrate DatabaseSecurity** into data access layer
3. **Integrate ValidationMiddleware** into API controllers
4. **Integrate OutputEncoder** into response generation
5. **Update vulnerable dependencies**
6. **Rebuild Docker images** with security patches

---

**Report Generated:** 2025-10-30 18:21:45
**Tools Used:** composer audit, Trivy (optional), hadolint (optional), custom static analysis
