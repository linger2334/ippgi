# 当前价格采集功能

## 功能说明

在每天早上 9:00 清除缓存并刷新材料列表之前，系统会自动采集并保存所有当前的原材料价格到数据库中。这确保了价格数据的连续性和完整性。

## 工作原理

### 1. 数据来源

- **API 接口：** 材料列表 API (`https://api.rendui.com/v1/jec/rendui/prices/daily`)
- **数据字段映射：**
  - API 字段 `lastprice` → 数据库字段 `price_cny`
  - API 字段 `lastpriceTax` → 数据库字段 `price_tax_cny`
  - 自动转换为 USD：`price_usd` 和 `price_tax_usd`

### 2. 采集内容

采集所有 6 种材料的当前价格：

| 材料代码 | 中文名称 | 数据库表 |
|---------|---------|---------|
| GI | 民用镀锌 | `wp_prices_gi` |
| GL | 镀铝锌 | `wp_prices_gl` |
| PPGI | 彩涂 | `wp_prices_ppgi` |
| HRC | 热卷 | `wp_prices_hrc` |
| CRC Hard | 轧硬 | `wp_prices_crc_hard` |
| AL | 光铝 | `wp_prices_al` |

每种材料包含所有宽度和厚度的规格组合。

### 3. 数据处理

1. 从 API 获取当前价格列表
2. 获取当前汇率（CNY to USD）
3. 对每条记录：
   - 提取价格信息（`lastprice`, `lastpriceTax`）
   - 转换为 USD
   - 保存到对应的数据库表
   - 如果记录已存在（相同 `product_spec` 和 `statistics_time`），则更新

## 使用方法

### 手动运行

```bash
cd /Users/linger3048/Sites/php81.test/ippgi
php collect-current-prices.php
```

### 定时任务（推荐）

在 crontab 中添加定时任务，每天早上 8:00 运行（在 9:00 清除缓存之前）：

```bash
# 编辑 crontab
crontab -e

# 添加以下行
0 8 * * * cd /Users/linger3048/Sites/php81.test/ippgi && php collect-current-prices.php >> /var/log/ippgi-price-collection.log 2>&1
```

## 输出示例

```
=== Collect Current Prices ===

Fetching current prices from API...

================================================================================
Collection Results
================================================================================

✅ Collection completed successfully!

Summary:
  Total saved: 398 records
  Total failed: 0 records
  Duration: 1.2 seconds
  Exchange rate: 6.9607 CNY per USD
  Statistics date: 2026-01-22 00:00:00
  Collected at: 2026-01-23 02:48:55

Results by material:
--------------------------------------------------------------------------------
✅ GI: 56 saved, 0 failed, 0 skipped
✅ GL: 94 saved, 0 failed, 0 skipped
✅ PPGI: 139 saved, 0 failed, 0 skipped
✅ HRC: 5 saved, 0 failed, 0 skipped
✅ CRC Hard: 94 saved, 0 failed, 0 skipped
✅ AL: 10 saved, 0 failed, 0 skipped

================================================================================
Database Statistics
================================================================================

GI:
  Total records: 38,257
  Latest date: 2026-01-23 00:00:00
  Today's records: 56

[... 其他材料统计 ...]
```

## 数据库表结构

每个材料表包含以下字段：

| 字段名 | 类型 | 说明 |
|-------|------|------|
| `id` | bigint | 产品规格 ID |
| `product_spec` | varchar(255) | 产品规格字符串 |
| `statistics_time` | datetime | 统计时间 |
| `timestamp` | bigint | Unix 时间戳 |
| `price_cny` | decimal(10,2) | 价格（人民币） |
| `price_usd` | decimal(10,2) | 价格（美元） |
| `price_tax_cny` | decimal(10,2) | 含税价格（人民币） |
| `price_tax_usd` | decimal(10,2) | 含税价格（美元） |
| `exchange_rate` | decimal(10,6) | 汇率 |
| `site_id` | varchar(50) | 站点 ID |
| `category_id` | varchar(50) | 分类 ID |
| `width` | varchar(20) | 宽度 |
| `thickness` | varchar(20) | 厚度 |
| `created_at` | datetime | 创建时间 |
| `updated_at` | datetime | 更新时间 |

**唯一约束：** `product_spec` + `statistics_time`

## 与历史数据的关系

### 历史数据导入

- **来源：** 历史数据 API
- **字段名：** `price` 和 `priceTax`
- **时间范围：** 2022-2026 年（4 年）
- **记录数：** 475,109 条

### 当前价格采集

- **来源：** 材料列表 API
- **字段名：** `lastprice` 和 `lastpriceTax`
- **频率：** 每天早上 8:00
- **记录数：** 每次约 398 条（所有当前规格）

### 数据整合

两种数据源的记录保存在同一个数据库表中：
- 历史数据填充了过去的价格记录
- 当前价格采集持续添加最新的价格记录
- 使用 `REPLACE` 语句避免重复，如果记录已存在则更新

## 监控和维护

### 检查采集状态

```bash
# 查看最近的采集日志
tail -f /var/log/ippgi-price-collection.log

# 检查数据库中今天的记录数
php -r "
define('WP_USE_THEMES', false);
require('/Users/linger3048/Sites/php81.test/ippgi/wp-load.php');
global \$wpdb;
\$today = current_time('Y-m-d');
\$count = \$wpdb->get_var(\"SELECT COUNT(*) FROM {\$wpdb->prefix}prices_gi WHERE DATE(statistics_time) = '\$today'\");
echo \"Today's GI records: \$count\n\";
"
```

### 常见问题

**Q: 如果 API 请求失败怎么办？**
A: 脚本会记录错误信息，但不会中断。下次运行时会重新尝试。

**Q: 如果某个材料没有数据怎么办？**
A: 脚本会跳过该材料，继续处理其他材料。

**Q: 数据会重复吗？**
A: 不会。使用 `REPLACE` 语句，相同的 `product_spec` 和 `statistics_time` 只会保留一条记录。

**Q: 汇率从哪里获取？**
A: 使用当前汇率（从欧洲央行数据），与历史数据导入使用的汇率系统相同。

## 技术实现

### 核心类

**`IPPGI_Prices_Current_Price_Collector`**
- 位置：`wp-content/plugins/ippgi-prices/includes/class-current-price-collector.php`
- 功能：
  - `collect_all_current_prices()` - 采集所有材料的当前价格
  - `save_material_prices()` - 保存单个材料的价格
  - `prepare_price_data()` - 准备数据库插入数据
  - `get_statistics()` - 获取数据库统计信息

### 依赖关系

```
collect-current-prices.php (脚本)
    ↓
IPPGI_Prices_Current_Price_Collector (采集器)
    ↓
IPPGI_Prices_API_Client (API 客户端)
    ↓
IPPGI_Prices_Currency_Converter (汇率转换器)
```

## 下一步

1. **设置定时任务：** 在 crontab 中添加每天 8:00 的定时任务
2. **监控日志：** 定期检查采集日志，确保数据正常采集
3. **数据验证：** 定期验证数据库中的记录数和最新日期

---

*创建时间：2026-01-23*
*版本：1.0*
