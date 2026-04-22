# 定时任务系统 - 价格数据自动采集

## 系统概述

IPPGI 价格插件使用 WordPress 的 WP-Cron 系统，自动执行以下任务：

**凌晨 00:10（每天一次）：**
- **保存历史价格快照到数据库**
- **同时保存缓存中的汇率快照**
- **若缓存缺失，回源抓取最新价格列表兜底**

**09:10 - 17:10（每小时一次）：**
- **清除实时价格缓存和汇率缓存**
- **保留价格列表缓存**
- **从 API 增量获取最新价格数据**

## 运行时间

| 时间 | 任务类型 | 执行内容 |
|------|---------|---------|
| **00:10** | 午夜快照 | 保存缓存中的业务日价格和汇率快照 |
| 09:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 10:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 11:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 12:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 13:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 14:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 15:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 16:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |
| 17:10 | 每小时更新 | 清除实时价/汇率缓存 + 增量获取新数据 |

## 工作流程

### 凌晨 00:10 - 午夜快照任务

```
保存历史价格快照到数据库
├─ 优先从缓存读取业务日价格列表
├─ 若缓存缺失则回源抓取最新价格列表兜底
├─ 提取所有材料的价格数据
├─ 提取并保存缓存中的汇率快照
└─ 保存到 6 个数据库表
```

**执行时间：** ~0.24 秒
**保存记录：** 398 条

### 09:10 - 17:10 - 每小时更新任务

```
1. 清除实时价格缓存和汇率缓存
   ├─ 保留价格列表缓存
   └─ 清除实时价格缓存

2. 增量获取最新价格数据
   ├─ 逐个分类从 API 获取最新数据
   ├─ 获取最新汇率并统一换算 USD
   └─ 更新缓存
```

**执行时间：** ~0.84 秒

### 为什么要先保留午夜快照再做白天刷新？

**关键原因：** 保留历史数据的连续性

- **00:10：**
  1. 先把缓存中的业务日价格和汇率快照落库 ✅
  2. 不主动刷新汇率
  3. 不重算缓存中的 USD 值
- **09:10 之后：**
  1. 再进入白天每小时增量刷新
  2. 清理实时价/汇率缓存
  3. 获取今天的新价格
- **结果：** 历史快照与白天实时缓存各自职责清晰，没有数据丢失

如果午夜时先清缓存再落库，缓存里的业务日价格快照就可能丢失。

## 自动化配置

### 插件激活时自动设置

当 IPPGI Prices 插件激活时，系统会自动：

1. 注册 WP-Cron 钩子
2. 安排 1 个午夜任务（00:10）和 9 个白天任务（09:10-17:10）
3. 开始自动执行

### 检查定时任务状态

```php
// 在 WordPress 管理后台或通过代码检查
$scheduler = new IPPGI_Prices_Scheduler($api_client, $cache_manager);
$next_runs = $scheduler->get_next_scheduled_times();

foreach ($next_runs as $run) {
    echo "Next run: {$run['datetime']} (hour: {$run['hour']})\n";
}
```

### 查看最后一次运行

```php
$last_run = $scheduler->get_last_run_info();

echo "Last run: {$last_run['datetime']}\n";
echo "Prices saved: {$last_run['prices_saved']} records\n";
echo "Execution time: {$last_run['execution_time']} seconds\n";
```

## 数据采集详情

### 每次采集的数据量

| 材料 | 规格数量 |
|------|---------|
| GI (民用镀锌) | 56 |
| GL (镀铝锌) | 94 |
| PPGI (彩涂) | 139 |
| HRC (热卷) | 5 |
| CRC Hard (轧硬) | 94 |
| AL (光铝) | 10 |
| **总计** | **398** |

### 数据采集量

**每天保存一次（00:10 午夜快照）：**
- **每天：** 398 条记录左右（业务日快照）
- **每月（30 天）：** 约 11,940 条记录
- **每年：** 约 145,270 条记录

### 数据存储

所有价格数据保存在 6 个数据库表中：
- `wp_prices_gi`
- `wp_prices_gl`
- `wp_prices_ppgi`
- `wp_prices_hrc`
- `wp_prices_crc_hard`
- `wp_prices_al`

每条记录包含：
- 价格（CNY 和 USD）
- 含税价格（CNY 和 USD）
- 汇率
- 时间戳
- 产品规格信息

## 日志和监控

### 查看日志

WordPress 错误日志会记录每次任务的执行情况：

```bash
# 查看 WordPress 调试日志
tail -f /path/to/wordpress/wp-content/debug.log | grep "IPPGI Prices"
```

### 日志示例

```
IPPGI Prices: Starting scheduled task at 2026-01-23 09:10:00 (hour: 9)
IPPGI Prices: Cleared real-time and exchange rate caches - Price list kept: yes, Real-time prices: 2, Exchange rate: yes
IPPGI Prices: Refreshed exchange rate for hourly task: 6.9607 CNY per USD
IPPGI Prices: Incremental price list refresh complete. Updated: 6 categories, Errors/Empty: 0 categories
IPPGI Prices: Completed scheduled task in 1.09 seconds
```

### 监控指标

可以通过 WordPress 选项表监控：

```php
$last_run = get_option('ippgi_prices_last_run');

// 检查关键指标
if ($last_run['prices_collected'] && $last_run['prices_saved'] > 0) {
    echo "✅ 价格采集正常\n";
} else {
    echo "⚠️  价格采集失败\n";
}
```

## 手动触发（测试用）

### 方法 1：通过代码

```php
$scheduler->trigger_manual_run();
```

### 方法 2：通过测试脚本

```bash
php test-scheduler.php
```

### 方法 3：通过 WP-CLI

```bash
wp cron event run ippgi_prices_hourly_update
```

## 故障排除

### 问题 1：定时任务没有运行

**可能原因：**
- WP-Cron 被禁用
- 网站访问量太低（WP-Cron 需要访问触发）

**解决方案：**
```bash
# 使用系统 cron 代替 WP-Cron
# 在 wp-config.php 中添加：
define('DISABLE_WP_CRON', true);

# 在系统 crontab 中添加：
*/5 * * * * curl https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

### 问题 2：价格没有保存到数据库

**检查步骤：**
1. 查看错误日志
2. 检查数据库连接
3. 验证表结构是否正确
4. 确认 API 返回数据

**调试命令：**
```bash
php test-scheduler.php
```

### 问题 3：执行时间过长

**优化建议：**
- 检查 API 响应时间
- 优化数据库查询
- 考虑使用后台队列

## 性能考虑

### 当前性能

- **采集时间：** ~0.2 秒（398 条记录）
- **缓存清除：** ~0.1 秒
- **API 获取：** ~0.8 秒
- **总执行时间：** ~1.1 秒

### 资源使用

- **内存：** < 10 MB
- **CPU：** 低（主要是网络 I/O）
- **数据库：** 每次 398 条 REPLACE 操作

### 扩展性

当前系统可以轻松处理：
- ✅ 更多材料类型
- ✅ 更多规格组合
- ✅ 更高的采集频率

## 与历史数据的关系

### 数据来源对比

| 数据类型 | 来源 | 字段名 | 时间范围 |
|---------|------|--------|---------|
| 历史数据 | 历史 API | `price`, `priceTax` | 2022-2026 |
| 当前价格 | 材料列表 API | `lastprice`, `lastpriceTax` | 持续采集 |

### 数据整合

两种数据源的记录保存在同一个表中：
- 使用 `REPLACE` 语句避免重复
- 相同的 `product_spec` + `statistics_time` 只保留一条
- 历史数据提供过去的记录
- 定时任务提供持续的新记录

## 总结

✅ **完全自动化** - 无需手动干预
✅ **数据完整** - 保存前先采集，不丢失数据
✅ **高效执行** - 每次仅需 1 秒
✅ **可靠监控** - 完整的日志和状态记录
✅ **易于维护** - 集成在插件中，随插件启用/禁用

---

*更新时间：2026-01-23*
*版本：2.0 - 集成到定时任务系统*
