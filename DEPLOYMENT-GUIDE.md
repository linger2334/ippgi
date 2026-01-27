# IPPGI 服务器部署指南

本文档记录了 IPPGI 项目在生产服务器上的部署步骤和注意事项。

## 环境要求

- Ubuntu 22.04 LTS
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Nginx / Apache
- WP-CLI（推荐）

## 部署步骤

### 1. WordPress 基础配置

编辑 `wp-config.php`，确保以下配置正确：

```php
<?php
// 数据库配置
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');

// 表前缀
$table_prefix = 'ippgi_';

// ========== 重要：WP-Cron 配置 ==========
// 禁用 WordPress 内置 cron 触发（依赖系统 cron）
define('DISABLE_WP_CRON', true);

// ========== 重要：调试配置（只定义一次！）==========
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 内存限制
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

**注意**：确保 `WP_DEBUG` 只定义一次，避免重复定义导致 PHP 警告。

### 2. 设置系统 Cron

WordPress 的 WP-Cron 依赖网站访问来触发，在生产环境中不可靠。需要设置系统级 cron 来定时触发。

```bash
# 编辑 crontab
crontab -e
```

添加以下内容（替换路径为实际 WordPress 安装路径）：

```bash
# IPPGI WP-Cron 触发器 - 每分钟执行
* * * * * cd /home/html/www/ippgi && /usr/bin/php wp-cron.php >> /var/log/wp-cron.log 2>&1
```

**重要**：
- 使用 `php wp-cron.php` 直接执行，而不是 `curl` 命令
- 如果使用 `curl`，URL 必须用引号包裹：`curl -s "https://example.com/wp-cron.php?doing_wp_cron"`

### 3. 激活插件并验证任务调度

激活 IPPGI Prices 插件后，验证定时任务是否正确调度：

```bash
# 使用 WP-CLI 查看所有 IPPGI 相关的定时任务
wp cron event list --path=/home/html/www/ippgi --allow-root | grep ippgi
```

应该看到 10 个任务：
- 1 个午夜任务：`ippgi_prices_midnight_collection`（00:00）
- 9 个小时任务：`ippgi_prices_hourly_update`（09:00-17:00）

示例输出：
```
ippgi_prices_midnight_collection    2026-01-28 00:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 09:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 10:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 11:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 12:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 13:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 14:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 15:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 16:00:00    1 day
ippgi_prices_hourly_update          2026-01-28 17:00:00    1 day
```

### 4. 手动调度任务（如果缺失）

如果任务未正确调度，使用以下脚本手动调度：

```bash
cd /home/html/www/ippgi && php -r "
require_once('wp-load.php');

// 清除现有的 IPPGI 任务
\$cron = _get_cron_array();
foreach (\$cron as \$ts => \$hooks) {
    if (isset(\$hooks['ippgi_prices_hourly_update'])) {
        foreach (\$hooks['ippgi_prices_hourly_update'] as \$key => \$event) {
            wp_unschedule_event(\$ts, 'ippgi_prices_hourly_update', \$event['args']);
        }
    }
    if (isset(\$hooks['ippgi_prices_midnight_collection'])) {
        foreach (\$hooks['ippgi_prices_midnight_collection'] as \$key => \$event) {
            wp_unschedule_event(\$ts, 'ippgi_prices_midnight_collection', \$event['args']);
        }
    }
}
echo \"Cleared existing tasks\n\";

// 调度午夜任务
\$tomorrow = strtotime('tomorrow 00:00:00');
wp_schedule_event(\$tomorrow, 'daily', 'ippgi_prices_midnight_collection');
echo \"Scheduled midnight collection at: \" . date('Y-m-d H:i:s', \$tomorrow) . \"\n\";

// 调度小时任务 (09:00 - 17:00)
\$today = date('Y-m-d');
\$now = current_time('timestamp');
\$hours = [9, 10, 11, 12, 13, 14, 15, 16, 17];

foreach (\$hours as \$hour) {
    \$time = strtotime(\"\$today \$hour:00:00\");
    if (\$time < \$now) {
        \$time = strtotime('+1 day', \$time);
    }
    wp_schedule_event(\$time, 'daily', 'ippgi_prices_hourly_update', [\$hour]);
    echo \"Scheduled hour \$hour at: \" . date('Y-m-d H:i:s', \$time) . \"\n\";
}

echo \"\nAll tasks scheduled successfully!\n\";
"
```

### 5. 验证日志写入

检查 debug.log 是否正常写入：

```bash
# 测试日志写入
cd /home/html/www/ippgi && php -r "
require_once('wp-load.php');
error_log('IPPGI Test: Log entry at ' . date('Y-m-d H:i:s'));
echo \"Test log written\n\";
"

# 查看日志
tail -10 /home/html/www/ippgi/wp-content/debug.log
```

### 6. 手动触发任务测试

```bash
# 手动触发小时任务
wp cron event run ippgi_prices_hourly_update --path=/home/html/www/ippgi --allow-root

# 手动触发午夜任务
wp cron event run ippgi_prices_midnight_collection --path=/home/html/www/ippgi --allow-root

# 查看执行日志
tail -30 /home/html/www/ippgi/wp-content/debug.log
```

## 常见问题排查

### 问题 1：定时任务不执行

**症状**：debug.log 中没有新的任务执行日志

**排查步骤**：

1. 检查系统 cron 是否运行：
   ```bash
   systemctl status cron
   ```

2. 检查 cron 任务配置：
   ```bash
   crontab -l
   ```

3. 检查 cron 执行日志：
   ```bash
   grep -i cron /var/log/syslog | tail -20
   ```

4. 手动执行 wp-cron.php 测试：
   ```bash
   cd /home/html/www/ippgi && php wp-cron.php 2>&1
   ```

### 问题 2：WP_DEBUG 重复定义警告

**症状**：`PHP Warning: Constant WP_DEBUG already defined`

**解决**：检查 wp-config.php，确保 `WP_DEBUG` 只定义一次。

```bash
grep -n "WP_DEBUG" /home/html/www/ippgi/wp-config.php
```

### 问题 3：任务调度时间错乱

**症状**：小时任务不在整点执行

**解决**：使用上面的手动调度脚本重新调度所有任务。

### 问题 4：curl 命令无法触发 cron

**症状**：使用 curl 调用 wp-cron.php 但任务不执行

**原因**：URL 中的 `?` 是 shell 特殊字符，未加引号会导致问题

**解决**：改用 PHP 直接执行：
```bash
cd /path/to/wordpress && /usr/bin/php wp-cron.php
```

## 监控建议

### 日志监控

定期检查 debug.log 确认任务正常执行：

```bash
# 查看最近的任务执行记录
grep "IPPGI Prices" /home/html/www/ippgi/wp-content/debug.log | tail -50
```

### 数据库监控

检查价格数据是否正常保存：

```bash
wp db query "SELECT COUNT(*) as count, DATE(created_at) as date FROM ippgi_prices_ppgi GROUP BY DATE(created_at) ORDER BY date DESC LIMIT 7" --path=/home/html/www/ippgi --allow-root
```

## 定时任务说明

| 任务 | 执行时间 | 功能 |
|------|----------|------|
| `ippgi_prices_midnight_collection` | 每天 00:00 | 保存昨日价格数据到数据库 |
| `ippgi_prices_hourly_update` | 每天 09:00-17:00 | 清除缓存并获取最新价格 |

## 更新日志

- **2026-01-27**：首次创建部署指南，记录 WP-Cron 问题排查过程
