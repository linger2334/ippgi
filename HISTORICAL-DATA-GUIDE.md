# IPPGI Prices Plugin - 历史数据和货币转换功能

## 新增功能概述

### 1. 数据库表（6张表）

为每种材料创建了独立的历史价格数据表：

| 材料 | 表名 |
|-----|------|
| GI (民用镀锌) | `wp_prices_gi` |
| GL (镀铝锌) | `wp_prices_gl` |
| PPGI (彩涂) | `wp_prices_ppgi` |
| HRC (热卷) | `wp_prices_hrc` |
| CRC Hard (轧硬) | `wp_prices_crc_hard` |
| AL (光铝) | `wp_prices_al` |

**表结构:**
```sql
CREATE TABLE wp_prices_gi (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_spec varchar(255) NOT NULL,
  statistics_time datetime NOT NULL,
  timestamp bigint(20) NOT NULL,
  price_cny decimal(10,2) NOT NULL,
  price_usd decimal(10,2) NOT NULL,
  price_tax_cny decimal(10,2) NOT NULL,
  price_tax_usd decimal(10,2) NOT NULL,
  exchange_rate decimal(10,6) NOT NULL,
  site_id varchar(50) NOT NULL,
  category_id varchar(50) NOT NULL,
  width varchar(20) NOT NULL,
  thickness varchar(20) NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_spec_time (product_spec, statistics_time),
  KEY idx_statistics_time (statistics_time),
  KEY idx_product_spec (product_spec),
  KEY idx_timestamp (timestamp)
);
```

### 2. 货币转换功能

**汇率来源:** 中国银行官网 (https://www.boc.cn/sourcedb/whpj/)

**转换逻辑:**
- 自动从中国银行获取实时汇率
- 当前汇率缓存24小时
- 历史汇率存储在数据库中永久保存
- 所有价格数据同时保存CNY和USD两种货币
- 默认对外展示USD价格

**历史汇率支持:**
- ✅ 系统现在支持使用历史汇率转换历史价格数据
- ✅ 每条历史记录使用其对应日期的汇率进行转换
- ✅ 历史汇率存储在 `wp_prices_exchange_rates` 表中
- ⚠️ 需要手动导入历史汇率数据（见下方说明）

**当前汇率:** 1 USD = 6.9607 CNY

**转换示例:**
- 使用2026-01-23汇率(6.9607): 3450 CNY = 495.64 USD
- 使用2024-06-01汇率(7.2672): 3450 CNY = 474.74 USD
- 使用2023-01-01汇率(6.8972): 3450 CNY = 500.20 USD

### 3. 历史数据导入

**数据来源API:**
```
GET https://api.rendui.com/v1/jec/rendui/prices/statistics
```

**参数:**
- `siteId`: 1457210664971423746 (博兴地区)
- `productSpec`: 产品规格 (如: 1457211766760558593_1200_0.4_民用镀锌)
- `from`: 开始时间 (2022-01-23 00:00:00)
- `to`: 结束时间 (2026-01-23 00:00:00)
- `categoryId`: 材料类别ID

**导入流程:**
1. 获取当前价格列表，提取所有产品规格
2. 对每个产品规格请求历史数据
3. 过滤无效数据（id=0或price=0）
4. 对每条记录使用其对应日期的历史汇率进行转换（CNY→USD）
5. 存储到对应材料的数据表

## 使用说明

### 导入历史汇率数据（重要！）

在导入历史价格数据之前，建议先导入历史汇率数据以确保转换准确：

```bash
cd /Users/linger3048/Sites/php81.test/ippgi
php import-exchange-rates.php
```

**说明:**
- 脚本包含2022-2026年的示例汇率数据
- 建议替换为实际的历史汇率数据
- 可以从以下来源获取真实汇率：
  - 中国银行历史数据
  - ExchangeRate-API.com
  - Fixer.io
  - Open Exchange Rates

**手动添加汇率:**
```php
global $wpdb;
$table_name = $wpdb->prefix . 'prices_exchange_rates';
$wpdb->replace($table_name, array(
    'rate_date' => '2024-06-15',
    'rate' => 7.2672,
    'created_at' => current_time('mysql'),
), array('%s', '%f', '%s'));
```

### 运行历史数据导入

```bash
cd /Users/linger3048/Sites/php81.test/ippgi
php import-historical-data.php
```

**注意事项:**
- 导入过程可能需要几分钟到几十分钟
- 会发起大量API请求（每个产品规格一次）
- 建议在非高峰时段运行
- 脚本会要求确认后才开始导入

### API响应格式变化

所有价格API现在都包含货币信息：

**实时价格API响应:**
```json
{
  "success": true,
  "data": {
    "result": {
      "price": 495.64,           // USD价格（默认）
      "price_cny": 3450,          // CNY价格
      "price_usd": 495.64,        // USD价格
      "taxPrice": 535.29,         // USD含税价
      "taxPrice_cny": 3726,       // CNY含税价
      "taxPrice_usd": 535.29,     // USD含税价
      "exchange_rate": 6.9607,    // 汇率
      "currency": "USD",          // 默认货币
      // ... 其他字段也都有_cny和_usd版本
    }
  }
}
```

**价格列表API响应:**
```json
{
  "success": true,
  "data": {
    "categories": {
      "GI": {
        "result": {
          "1200": [
            {
              "price": 495.64,
              "price_cny": 3450,
              "price_usd": 495.64,
              "exchange_rate": 6.9607,
              "currency": "USD",
              // ...
            }
          ]
        }
      }
    }
  }
}
```

### 数据库查询示例

**查询GI材料的历史价格:**
```php
$records = IPPGI_Prices_Database::get_historical_prices(
    'GI',                                    // 材料类型
    '1457211766760558593_1200_0.4_民用镀锌',  // 产品规格
    '2026-01-01 00:00:00',                   // 开始时间
    '2026-01-23 23:59:59'                    // 结束时间
);

foreach ($records as $record) {
    echo "{$record['statistics_time']}: ";
    echo "\${$record['price_usd']} USD (¥{$record['price_cny']} CNY)\n";
}
```

**插入历史价格记录:**
```php
$data = array(
    'product_spec' => '1457211766760558593_1200_0.4_民用镀锌',
    'statistics_time' => '2026-01-23 00:00:00',
    'timestamp' => time() * 1000,
    'price_cny' => 3450.00,
    'price_usd' => 495.64,
    'price_tax_cny' => 3726.00,
    'price_tax_usd' => 535.29,
    'exchange_rate' => 6.9607,
    'site_id' => '1457210664971423746',
    'category_id' => '1457211766760558593',
    'width' => '1200',
    'thickness' => '0.4',
);

$id = IPPGI_Prices_Database::insert_price_record('GI', $data);
```

## 新增文件

1. **class-database.php** - 数据库表管理（包含历史价格表和汇率表）
2. **class-currency-converter.php** - 货币转换（支持历史汇率）
3. **class-historical-importer.php** - 历史数据导入（使用日期特定汇率）
4. **import-historical-data.php** - 历史价格数据导入脚本
5. **import-exchange-rates.php** - 历史汇率数据导入脚本
6. **test-historical-rates.php** - 汇率功能测试脚本
7. **HISTORICAL-RATES-UPDATE.md** - 历史汇率功能详细文档

## 测试结果

✅ 所有组件测试通过：
- ✅ 7张数据库表创建成功（6张价格表 + 1张汇率表）
- ✅ 货币转换功能正常（当前汇率: 6.9607）
- ✅ 历史汇率功能正常（支持日期特定汇率）
- ✅ 历史数据API正常
- ✅ 数据库插入/查询正常
- ✅ 价格列表API包含USD价格
- ✅ 实时价格API包含USD价格
- ✅ 历史数据导入使用日期特定汇率

**测试示例:**
```
2026-01-23汇率: 6.9607 → 3450 CNY = 495.64 USD
2024-06-01汇率: 7.2672 → 3450 CNY = 474.74 USD
2023-01-01汇率: 6.8972 → 3450 CNY = 500.20 USD
```

## 注意事项

1. **汇率更新:**
   - 当前汇率每24小时自动更新一次
   - 历史汇率永久存储在数据库中
2. **数据一致性:**
   - 历史数据导入时使用对应日期的历史汇率
   - 如果某个日期的汇率不存在，会使用当前汇率作为后备
   - 建议在导入历史价格数据前先导入历史汇率数据
3. **API限制:** 历史数据API可能有请求频率限制，导入时已添加延迟
4. **数据去重:** 使用 `(product_spec, statistics_time)` 作为唯一键，避免重复数据
5. **缓存清理:** 更新货币转换后需要清除缓存才能看到新数据

## 下一步建议

1. **前端集成:**
   - 在价格表格中显示USD价格
   - 添加货币切换功能（USD/CNY）
   - 显示历史价格图表

2. **数据维护:**
   - 定期运行历史数据导入（每日/每周）
   - 监控汇率变化
   - 备份历史数据

3. **性能优化:**
   - 考虑使用队列处理大批量导入
   - 添加导入进度显示
   - 优化数据库查询索引
