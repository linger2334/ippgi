# IPPGI Prices API - 使用说明

## ✅ API已成功部署！

插件已激活并正常工作。所有6个材料类别的价格数据都可以通过REST API获取。

## API端点

### 1. 获取价格列表（所有材料）

**URL:**
```
http://yoursite.com/ippgi/?rest_route=/ippgi-prices/v1/prices
```

**响应示例:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "date": "2026-01-22 19:34:11",
    "categories": {
      "GI": {
        "success": true,
        "result": {
          "1200": [
            {
              "thickness": "0.4",
              "price": 3450,
              "taxPrice": 3726,
              "width": "1200",
              "material": "民用镀锌",
              "riseAndFall": -40,
              "lastprice": 3450,
              "updateTime": 1769045193094
            }
          ],
          "1000": [...],
          "1219": [...],
          "1250": [...]
        }
      },
      "GL": { ... },
      "PPGI": { ... },
      "HRC": { ... },
      "CRC Hard": { ... },
      "AL": { ... }
    },
    "errors": {},
    "fetched_at": "2026-01-22 19:34:11"
  },
  "cached": true
}
```

### 2. 获取实时价格（单个材料规格）

**URL:**
```
http://yoursite.com/ippgi/?rest_route=/ippgi-prices/v1/price&product_type=GI&width=1200&thickness=0.4
```

**参数:**
- `product_type` (必需): 材料类型 (GI, GL, PPGI, HRC, "CRC Hard", AL)
- `width` (必需): 宽度(mm) - 例如: 1000, 1200
- `thickness` (必需): 厚度 - 例如: 0.4, 0.45, 0.09
- `date` (可选): 日期 YYYY-MM-DD

**响应示例:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "code": 200,
    "result": {
      "thickness": "0.4",
      "price": 3450,
      "taxPrice": 3726,
      "width": "1200",
      "material": "民用镀锌",
      "riseAndFall": -40,
      "riseRange": -1.15,
      "lastprice": 3450,
      "lastWeekDiff": -80,
      "lastMonthDiff": -130,
      "lastYearsDiff": -360,
      "openingPrice": 0,
      "closePrice": null,
      "updateTime": 1769045193094
    }
  },
  "cached": true
}
```

**注意:** 如果指定的规格不存在，`result` 将为 `null`。

## 前端集成示例

### JavaScript Fetch API

```javascript
// 获取所有价格列表
fetch('/ippgi/?rest_route=/ippgi-prices/v1/prices')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('所有材料价格:', data.data.categories);
      console.log('GI价格:', data.data.categories.GI);
      console.log('PPGI价格:', data.data.categories.PPGI);
    }
  });

// 获取特定规格的实时价格
const params = new URLSearchParams({
  product_type: 'PPGI',
  width: 1000,
  thickness: 0.09
});

fetch(`/ippgi/?rest_route=/ippgi-prices/v1/price&${params}`)
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('PPGI 0.09*1000 价格:', data.data);
    }
  });
```

### jQuery

```javascript
// 获取价格列表
$.getJSON('/ippgi/?rest_route=/ippgi-prices/v1/prices', function(data) {
  if (data.success) {
    // 处理价格数据
    console.log(data.data.categories);
  }
});

// 获取实时价格
$.getJSON('/ippgi/?rest_route=/ippgi-prices/v1/price', {
  product_type: 'PPGI',
  width: 1000,
  thickness: 0.09
}, function(data) {
  if (data.success) {
    console.log(data.data);
  }
});
```

## 数据结构说明

### 价格列表数据结构

每个材料类别包含按宽度分组的价格数据：

```json
{
  "GI": {
    "success": true,
    "result": {
      "1200": [
        {
          "thickness": "0.4",
          "price": 3450,
          "taxPrice": 3726,
          "width": "1200",
          "material": "民用镀锌",
          "riseAndFall": -40,
          "lastprice": 3450,
          "updateTime": 1769045193094
        }
      ]
    }
  }
}
```

### 关键字段说明

- `price`: 裸价（不含税）
- `taxPrice`: 含税价格
- `riseAndFall`: 涨跌幅（相对于上一次）
- `thickness`: 厚度
- `width`: 宽度
- `material`: 材料名称
- `updateTime`: 更新时间戳

## 自动更新机制

- **更新频率**: 每天9:00-17:00，每小时整点自动更新
- **缓存时间**: 1小时
- **更新流程**:
  1. 清除所有缓存
  2. 从API获取最新数据
  3. 缓存到本地
  4. 前端请求时直接返回缓存数据

## 测试命令

```bash
# 测试价格列表API
curl "http://php81.test/ippgi/?rest_route=/ippgi-prices/v1/prices"

# 测试实时价格API
curl "http://php81.test/ippgi/?rest_route=/ippgi-prices/v1/price&product_type=PPGI&width=1000&thickness=0.09"

# 查看缓存统计（需要管理员权限）
curl "http://php81.test/ippgi/?rest_route=/ippgi-prices/v1/cache-stats"
```

## 材料类别对应关系

| 中文名称 | 英文缩写 | Category ID |
|---------|---------|-------------|
| 民用镀锌 | GI | 1457211766760558593 |
| 镀铝锌 | GL | 1683315093109178369 |
| 彩涂 | PPGI | 1482328115005964290 |
| 热卷 | HRC | 1457211813719986177 |
| 轧硬 | CRC Hard | 1457211766760558594 |
| 光铝 | AL | 1457211893311098881 |

## 注意事项

1. **日期逻辑**: 如果当前时间在00:00-09:00之间，API会使用昨天的日期
2. **缓存策略**: 数据会缓存1小时，减少API调用次数
3. **错误处理**: 如果某个类别获取失败，其他类别的数据仍会正常返回
4. **URL格式**: 如果 `/wp-json/` 格式不工作，使用 `?rest_route=` 格式

## 下一步

现在可以在主题中集成这个API来显示价格数据了！
