# IPPGI Prices Plugin - 历史汇率功能更新

## 更新内容

### 1. 历史汇率支持

现在系统支持使用历史汇率来转换历史价格数据，而不是对所有历史数据使用当前汇率。

**新增数据库表：**
- `wp_prices_exchange_rates` - 存储历史汇率数据

**表结构：**
```sql
CREATE TABLE wp_prices_exchange_rates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rate_date date NOT NULL,
  rate decimal(10,6) NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_date (rate_date),
  KEY idx_rate_date (rate_date)
);
```

### 2. 使用方法

**获取特定日期的汇率：**
```php
// 获取当前汇率
$current_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate();

// 获取历史汇率（YYYY-MM-DD格式）
$historical_rate = IPPGI_Prices_Currency_Converter::get_exchange_rate('2024-06-01');
```

**历史数据导入：**
现在导入历史数据时，系统会自动为每条记录使用对应日期的汇率：

```bash
php import-historical-data.php
```

### 3. 汇率数据来源

**当前实现：**
- 尝试从中国银行网站获取历史汇率
- 如果获取失败，使用当前汇率作为后备
- 所有汇率都会缓存到数据库中

**注意事项：**
由于中国银行网站的HTML结构可能变化，历史汇率抓取可能不稳定。建议使用以下替代方案之一：

### 4. 替代方案

#### 方案A：使用第三方汇率API

推荐使用专业的汇率API服务：
- [ExchangeRate-API](https://www.exchangerate-api.com/) - 免费层级每月1500次请求
- [Fixer.io](https://fixer.io/) - 提供历史汇率数据
- [Open Exchange Rates](https://openexchangerates.org/) - 支持历史数据

#### 方案B：手动导入历史汇率

如果需要精确的历史汇率，可以手动导入：

```php
<?php
// 手动导入历史汇率脚本
require('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'prices_exchange_rates';

// 历史汇率数据（示例）
$historical_rates = array(
    '2022-01-01' => 6.3757,
    '2022-06-01' => 6.6981,
    '2023-01-01' => 6.8972,
    '2023-06-01' => 7.2258,
    '2024-01-01' => 7.0999,
    '2024-06-01' => 7.2672,
    '2025-01-01' => 7.3258,
    '2025-06-01' => 7.2456,
    '2026-01-01' => 6.9607,
    // ... 添加更多日期
);

foreach ($historical_rates as $date => $rate) {
    $wpdb->replace(
        $table_name,
        array(
            'rate_date' => $date,
            'rate' => $rate,
            'created_at' => current_time('mysql'),
        ),
        array('%s', '%f', '%s')
    );
    echo "Imported rate for {$date}: {$rate}\n";
}

echo "Import complete!\n";
?>
```

#### 方案C：使用月度平均汇率

对于不需要每日精确汇率的场景，可以使用月度平均汇率：

```php
// 月度平均汇率（2022-2026）
$monthly_rates = array(
    '2022-01' => 6.3757,
    '2022-02' => 6.3521,
    // ... 每月一个汇率
);

// 根据日期查找对应月份的汇率
$date = '2022-01-15';
$month = substr($date, 0, 7); // 2022-01
$rate = $monthly_rates[$month];
```

### 5. 测试历史汇率功能

运行测试脚本：
```bash
php test-historical-rates.php
```

### 6. 当前状态

✅ 历史汇率数据库表已创建
✅ 历史汇率查询功能已实现
✅ 历史数据导入已更新为使用日期特定汇率
⚠️ 中国银行历史汇率抓取需要优化或替换为其他数据源

### 7. 建议

**短期方案：**
1. 使用当前汇率作为后备（已实现）
2. 手动导入关键日期的历史汇率

**长期方案：**
1. 集成专业汇率API服务
2. 定期更新历史汇率数据
3. 实现汇率数据的自动同步

### 8. 代码变更

**修改的文件：**
- `class-currency-converter.php` - 添加历史汇率支持
- `class-database.php` - 添加汇率表创建
- `class-historical-importer.php` - 更新为使用日期特定汇率

**新增文件：**
- `test-historical-rates.php` - 测试脚本

### 9. API响应格式

历史数据导入后，每条记录都包含其对应日期的汇率：

```json
{
  "product_spec": "1457211766760558593_1200_0.4_民用镀锌",
  "statistics_time": "2024-06-15 10:00:00",
  "price_cny": 3450.00,
  "price_usd": 474.89,
  "exchange_rate": 7.2672,
  "..."
}
```

注意 `exchange_rate` 字段现在反映的是该记录日期的汇率，而不是当前汇率。
