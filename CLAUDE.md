# IPPGI - 原材料价格行情网站

## 项目概述
这是一个基于 WordPress 的原材料价格行情展示网站，为用户提供实时和历史价格数据。

## 技术栈
- WordPress (最新版本)
- PHP 8.1
- 数据库：MySQL（通过 WordPress wpdb）
- 会员插件：Simple Membership Plugin
- 缓存：WordPress Transients API
- 定时任务：WP-Cron
- 设计：Figma（移动端优先，响应式适配 PC 端）

## 会员系统
- **插件**：Simple Membership Plugin
- **会员等级配置**：

| 等级 | SWPM Level ID | 说明 |
|-----|---------------|------|
| Guest | - | 未登录用户，仅查看当天价格 |
| Basic | 2 | 免费注册用户，查看当天价格 |
| Trial | 3 | 试用会员，查看完整历史数据和图表 |
| Plus | 4 | 付费高级会员，查看完整历史数据、图表、数据导出 |

## 展示的原材料种类（6种）

| 中文名称 | 英文名称 | 缩写 | Category ID | 规格宽度 (mm) |
|---------|---------|------|-------------|--------------|
| 民用镀锌 | Galvanized Steel | GI | 1457211766760558593 | 1000、1200、1219、1250 |
| 镀铝锌 | Galvalume Steel | GL | 1683315093109178369 | 1000、1200 |
| 彩涂 | Pre-painted Galvanized Iron | PPGI | 1482328115005964290 | 1000、1200 |
| 热卷 | Hot Rolled Coil | HRC | 1457211813719986177 | 1010、1500 |
| 轧硬 | Cold Rolled Hard Coil | CRC Hard | 1457211766760558594 | 1000、1200 |
| 光铝 | Aluminum Sheet | AL | 1457211893311098881 | 1000 |

**站点 ID**：1457210664971423746（博兴地区）

---

## 项目结构

### 核心插件
**位置**：`/wp-content/plugins/ippgi-prices/`

**类文件** (`/includes/`)：
- `class-database.php` - 数据库表管理
- `class-api-client.php` - 外部 API 客户端
- `class-cache-manager.php` - 缓存管理
- `class-rest-api.php` - REST API 端点
- `class-scheduler.php` - 定时任务调度
- `class-currency-converter.php` - 货币转换
- `class-historical-importer.php` - 历史数据导入
- `class-current-price-collector.php` - 当前价格采集

### 自定义主题
**位置**：`/wp-content/themes/ippgi/`
**当前版本**：1.10.1

**页面模板** (`/page-templates/`)：
- `page-prices.php` - 价格列表页面
- `page-price-detail.php` - 价格详情页面
- `page-subscribe.php` - 订阅/会员升级页面
- `page-profile.php` - 用户个人资料页面
- `page-favorites.php` - 收藏夹页面
- `page-login.php` - 登录页面
- `page-payment.php` - 支付页面
- `page-invite.php` - 邀请好友页面
- `page-terms.php` - 服务条款页面
- `page-privacy.php` - 隐私政策页面
- `page-contact.php` - 联系我们页面

**核心模板**：
- `front-page.php` - 首页模板
- `home.php` - 博客列表页模板
- `single.php` - 文章详情页模板
- `search.php` - 搜索结果页模板

**模板组件** (`/template-parts/`)：
- `price-table.php` - 价格表格组件
- `article-card.php` - 文章卡片组件
- `header-mobile.php` / `header-desktop.php` - 头部
- `footer-nav.php` - 底部导航
- `login-modal.php` - 登录弹窗
- `upgrade-prompt.php` - 升级提示

**功能文件** (`/inc/`)：
- `enqueue.php` - 资源加载
- `customizer.php` - 主题定制器
- `template-functions.php` - 模板函数（含 `ippgi_get_product_dimensions_range()`、`ippgi_format_dimensions_range()`）
- `membership.php` - 会员系统集成
- `announcement.php` - 公告系统

---

## 数据库表结构

**表前缀**：`ippgi_`（注意：不是默认的 `wp_`）

**7 张数据库表**：
1. `ippgi_prices_gi` - 民用镀锌价格表
2. `ippgi_prices_gl` - 镀铝锌价格表
3. `ippgi_prices_ppgi` - 彩涂价格表
4. `ippgi_prices_hrc` - 热卷价格表
5. `ippgi_prices_crc_hard` - 轧硬价格表
6. `ippgi_prices_al` - 光铝价格表
7. `ippgi_prices_exchange_rates` - 汇率表

**表字段**：
- `product_spec` - 产品规格
- `statistics_time` - 价格数据所属日期的凌晨时间（`YYYY-MM-DD 00:00:00`）
- `timestamp` - `statistics_time` 的 Unix 时间戳
- `price_cny` / `price_usd` - 价格（人民币/美元）
- `price_tax_cny` / `price_tax_usd` - 含税价格
- `exchange_rate` - 汇率（从缓存的价格数据中提取）
- `width` / `thickness` - 宽度/厚度
- `created_at` - 记录创建时间（实际保存时的时间）
- 唯一约束：`(product_spec, statistics_time)`

**当前数据量**（截至 2026-01-26）：

| 表 | 记录数 |
|---|-------|
| GI (民用镀锌) | 38,257 |
| GL (镀铝锌) | 85,634 |
| PPGI (彩涂) | 193,168 |
| HRC (热卷) | 7,310 |
| CRC Hard (轧硬) | 137,240 |
| AL (光铝) | 13,500 |
| **价格总计** | **475,109** |
| 汇率 | 1,479 |

**汇率数据范围**：2021-12-31 ~ 2026-01-23（日级别精度）

---

## 邀请奖励系统

### 功能说明
- 用户邀请好友注册成功后，邀请者获得 **3 天 Plus 会员** 奖励
- 如果邀请者已有 Plus 会员：延长到期时间
- 如果邀请者没有 Plus 会员：临时升级为 Plus（3天后自动降级）

### 工作流程
1. 用户访问 `/invite` 页面获取邀请链接
2. 邀请链接格式：`https://yoursite.com/?ref=xxxxxxxx`
3. 被邀请者点击链接，邀请码保存到 Cookie（30天有效）
4. 被邀请者通过 SWPM 注册
5. 系统自动奖励邀请者 3 天 Plus 会员

### 相关函数
- `ippgi_get_user_invite_link()` - 生成邀请链接
- `ippgi_save_referral_cookie()` - 保存邀请码到 Cookie
- `ippgi_process_referral()` - 处理推荐逻辑
- `ippgi_award_referral_bonus()` - 奖励 Plus 会员时间
- `ippgi_get_user_referral_count()` - 获取推荐人数
- `ippgi_get_user_total_bonus_days()` - 获取累计奖励天数
- `ippgi_get_invitation_history()` - 获取邀请历史记录
- `ippgi_mask_email()` - 邮箱脱敏显示（如 `john***@gmail.com`）

### 邀请页面 UI 实现
**模板文件**：`/page-templates/page-invite.php`

**页面结构**：
1. **标题区域**
   - 主标题：`Earn rewards for each friend you invite.`
   - 副标题：`Share with your friends and get rewards.`
   - 下方有分割线

2. **邀请链接区域**
   - 标题：`Get Your Exclusive Referral Link! Share it with friends and earn rewards!`（24px 粗体）
   - 邀请链接输入框（只读，宽度自适应内容，带阴影效果）
   - Copy 按钮（点击后显示 Toast 提示 "Copy Success!"）
   - 说明文字 + Contact us 链接（带下划线）
   - 下方有分割线

3. **邀请历史表格**
   - 标题：`Invitation History`
   - 表头：Number | Timestamp | Referred User
   - 表头四周圆角（8px）
   - 内容居中对齐
   - **日期格式**：`M d, Y`（如 `Jan 20, 2026`）

**CSS 样式**：位于 `/assets/css/components.css`
- `.invite-page` - 页面容器
- `.invite-link-section` - 邀请链接区域
- `.invite-link-box` - 链接输入框和按钮容器（垂直布局，居中）
- `.invite-link-box__input` - 输入框（带阴影：`box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4)`）
- `.invite-history` - 历史记录区域
- `.invite-history__table` - 表格样式（`border-collapse: separate`）

### 收藏页面 UI 实现
**模板文件**：`/page-templates/page-favorites.php`

**页面结构**：
1. **标题区域**
   - 主标题：`My Favorites selection of China steel and commodities.`
   - 副标题：`Shortlist your favorite price curve, indices, daily prices.`
   - 下方有分割线

2. **材料类型过滤器**
   - 默认显示 "Product" + 向下箭头
   - 点击弹出底部选择器（白色背景，圆角顶部）
   - 选择器顶部居中显示 "Product" 标题
   - 材料类型列表：PPGI、GI、GL、Aluminum Sheet、CRC Hard、HRC
   - 每项右侧有向右箭头
   - 支持切换选中/取消选中（再次点击同一项恢复默认状态）

3. **收藏列表**
   - 三等分布局：产品名称 | 规格 | 心形按钮
   - 内容居中显示，文字太长自动换行
   - 心形按钮点击可取消收藏（AJAX 请求）
   - 取消收藏后条目滑出动画移除

**收藏数据格式**：
- 存储在 user meta `ippgi_favorites`
- ID 格式：`type-productSpec`（如 `ppgi-1482328115005964290_1000_0.11_彩涂`）
- `ippgi_get_user_favorites()` 解析 productSpec 并返回结构化数据
- 类型键映射：`crc` → `crc_hard`，`aluminum` → `al`

**CSS 样式**：位于 `/assets/css/components.css`
- `.favorites-page` - 页面容器
- `.favorites-header` - 标题区域
- `.favorites-filter` - 过滤器按钮
- `.favorites-item` - 收藏条目（flex 三等分）
- `.favorites-item__heart` - 心形按钮
- `.material-selector` - 底部弹出选择器
- `.material-selector-backdrop` - 遮罩层

### 编辑个人资料页面 UI 实现
**模板文件**：`/page-templates/page-edit-profile.php`

**页面结构**：
1. **标题**：`Edit Member Profile`
2. **表单字段**：
   - Name（文本输入）
   - Country/Region（点击弹出国家选择器）
   - Company Name（文本输入）
   - Email（只读显示）
   - Mobile Number（电话输入，带验证）
3. **提交按钮**：
   - 默认禁用（灰色），表单有变化时启用（蓝色）
   - 通过 JavaScript 检测表单变化

**手机号验证**：
- 支持国际格式（+86、+1 等）
- 允许数字、空格、连字符、括号
- 最少 6 位数字，最多 15 位
- 输入时自动过滤非法字符
- 失焦时验证，无效显示红色错误提示

**国家选择器**：
- 点击 Country/Region 字段弹出模态框
- 支持搜索过滤
- 包含完整国家列表

### Toast 提示组件
**模板文件**：`/template-parts/toast.php`

**通用提示组件**，固定在屏幕中央，不占用文档流。全局可用，任何页面都可以调用。

**使用方法**：
```javascript
// 显示成功提示
ippgiToast.success('操作成功');

// 显示错误提示
ippgiToast.error('操作失败');

// 自定义类型和持续时间（毫秒）
ippgiToast.show('消息内容', 'success', 5000);
```

**加载方式**：
- 使用 `get_footer()` 的页面：自动加载（在 footer.php 中引入）
- 不使用 `get_footer()` 的页面：需手动引入 `get_template_part('template-parts/toast')`

**HTML 结构**：
```html
<div class="toast-message toast-message--success" id="ippgi-toast">
    <span class="toast-message__text">提示内容</span>
    <span class="toast-message__icon toast-message__icon--success">
        <svg><!-- 打钩/叉号图标 --></svg>
    </span>
</div>
```

**样式特点**：
- 固定定位在屏幕正中央
- 宽度 60%，最大宽度 300px
- 淡绿色背景（成功 #e8f5e9）/ 淡红色背景（错误 #ffebee）
- 黑色边框（1px #333）
- 绿色/红色文字（18px，font-weight: 600）
- 右侧圆形图标（绿色打钩/红色叉号）
- 默认 3 秒后自动淡出消失

**CSS 类**：
- `.toast-message` - 基础样式
- `.toast-message--success` - 成功状态
- `.toast-message--error` - 错误状态
- `.toast-message__text` - 文字内容
- `.toast-message__icon` - 图标容器
- `.toast-message__icon--success` - 绿色圆形打钩
- `.toast-message__icon--error` - 红色圆形叉号

**JavaScript API**：
- `ippgiToast.show(message, type, duration)` - 显示提示
- `ippgiToast.success(message, duration)` - 显示成功提示
- `ippgiToast.error(message, duration)` - 显示错误提示
- `ippgiToast.hide()` - 手动隐藏提示

### 升级提示 Banner
**模板文件**：`/template-parts/upgrade-prompt.php`

**浮动提示组件**，固定在屏幕右下角，提示用户升级到 Plus 会员。

**显示逻辑**（定义在 `footer.php`）：
```php
if (is_user_logged_in() && !ippgi_is_user_subscribed()) {
    get_template_part('template-parts/upgrade-prompt');
}
```

**显示条件**：

| 用户状态 | 是否显示 |
|---------|---------|
| 未登录（Guest） | ❌ 不显示 |
| Basic 会员（Level 2） | ✅ 显示 |
| Trial 会员（Level 3） | ✅ 显示 |
| Plus 会员（Level 4） | ❌ 不显示 |

**说明**：`ippgi_is_user_subscribed()` 函数只检查 Plus 会员，Trial 会员不被视为"已订阅"，因此也会显示升级提示。

**功能**：
- 点击 × 按钮可关闭
- 点击 "Upgrade" 跳转到订阅页面

### 价格列表页面 (Prices Page)
**模板文件**：`/page-templates/page-prices.php`

**页面结构**：
1. **页面标题**
   - 动态标题：`Price charts and tables of China {产品名称} and commodities`
   - 副标题/免责声明：`*These prices reflect the transaction prices within China...`

2. **产品选择器**
   - 下拉按钮显示 "Product" + 向下箭头
   - 点击展开下拉菜单，显示所有产品类型（PPGI、GI、GL、HRC、CRC Hard、Aluminum）
   - 选中项带勾号标记

3. **选中产品展示**
   - 居中显示当前选中的产品名称（如 "PPGI"）
   - 两侧带有装饰线

4. **Key Attributes**
   - 标题：`Key attributes`（粗体）
   - 动态内容：从缓存数据中获取厚度范围和宽度范围
   - 使用 `ippgi_get_product_dimensions_range()` 和 `ippgi_format_dimensions_range()` 函数

5. **宽度筛选标签**
   - 水平滚动的药丸式标签（如 1000mm、1200mm）
   - 宽度选项从缓存数据动态获取，不再硬编码
   - 点击标签本地切换数据（无需网络请求）

6. **更新时间和含税切换**
   - 左侧：更新时间（如 `Updated: Jan 30, 2026, 10:00 AM (UTC+8)`）
   - 右侧：圆形单选按钮 + "Tax-inclusive price" 标签

7. **价格表格**
   - 表头：Dimensions(mm) | Latest($) | Change($) | Historical
   - 蓝色表头背景（#e2f5fb）
   - Dimensions 列显示：`厚度*宽度 材料名称`（如 `0.4*1200 民用镀锌`）
   - Change 列根据涨跌显示不同颜色
   - Historical 列显示 "View >" 按钮，跳转到价格详情页

8. **Disclaimer**
   - 动态文本，产品名称根据当前选中类型变化

**URL 参数支持**：
- `?type=ppgi` - 直接指定产品类型（小写）
- `?category=PPGI` - 从首页跳转时使用（大写，自动映射）
- `?width=1000` - 指定默认宽度

**数据来源**：
- 所有价格数据从 `get_transient('ippgi_prices_price_list')` 缓存获取
- 通过 `window.ippgiPricesPage` 传递给 JavaScript
- 宽度切换和含税切换均在本地完成，无需 API 调用

**CSS 样式**：位于 `/assets/css/components.css`
- `.prices-page-header` - 页面标题区域
- `.product-selector` - 产品下拉选择器
- `.selected-product` - 选中产品展示
- `.key-attributes` - 属性描述区域
- `.width-filter` - 宽度筛选标签
- `.price-controls` - 更新时间和含税切换
- `.prices-table` - 价格表格
- `.prices-disclaimer` - 免责声明

### 价格详情页面 (Price Detail Page)
**模板文件**：`/page-templates/page-price-detail.php`
**WordPress 页面**：`/price-detail/`（需在后台创建页面并选择模板）

**页面结构**：
1. **页面标题**
   - 动态标题：`Price charts and tables of China {产品代码} and commodities`
   - 副标题/免责声明

2. **产品信息表格**
   - 蓝色表头（#e7f5fb）：Product | Dimensions(mm) | Favorite
   - 数据行：产品代码（粗体） | 规格（`厚度*宽度 材料名称`） | 心形收藏按钮
   - 带边框样式

3. **收藏功能**
   - 心形按钮：已收藏显示红色实心，未收藏显示空心轮廓
   - 点击切换收藏状态（AJAX 请求）
   - 操作后显示 Toast 提示（2秒）
   - 收藏 ID 格式：`type-productSpec`（如 `ppgi-1482328115005964290_1000_0.11_彩涂`）

4. **实时数据区域**
   - 标题：`Real-Time Data`
   - 圆形含税切换按钮
   - 大号当前价格（使用 `lastprice_usd` / `lastpriceTax_usd`）
   - 涨跌值和百分比（涨绿跌红，使用 `riseAndFall_usd` 和 `riseRange`）
   - 统计数据网格：
     - Avg：使用 `price_usd` / `priceTax_usd`
     - WoW：使用 `lastWeekDiff_usd` / `lastWeekDiffTax_usd`
     - MoM：使用 `lastMonthDiff_usd` / `lastMonthDiffTax_usd`
     - YoY：使用 `lastYearsDiff_usd` / `lastYearsDiffTax_usd`

5. **价格图表区域**
   - **日期范围选择器**：日历图标 + "Start Date ~ End Date"，点击打开底部日期选择器
   - 时间范围标签：TD | 1M | 6M | 1Y | 2Y | 3Y | 4Y（带滑动切换动画）
   - 图表标题：`{产品代码} Dimensions(mm):{规格}`
   - **TD 标签**：Canvas 绘制当天价格走势图（9:00 AM 后从 `/statistics` API 获取数据）
   - **1M/6M/1Y/2Y/3Y/4Y 标签**：从本地数据库 `/historical` API 获取历史数据
   - **自定义日期范围**：通过日期选择器选择任意开始和结束日期
   - **气球标记**：图表最高点和最低点显示 HTML 气球标记（椭圆形，亮蓝色 #7EE0FF）
   - **X 轴标签**：TD 显示时间（09:00-18:00），历史数据显示日期（MM-DD 格式）
   - **数据降采样**：数据量超过 300 点时自动降采样，保证图表流畅显示

6. **日期选择器（底部弹出）**
   - 与博客页面相同的底部弹出式日期选择器
   - 支持选择开始日期和结束日期
   - 日历导航（上一月/下一月）
   - 禁止选择未来日期
   - Clear 按钮清空选择，Confirm 按钮确认并加载数据
   - 选择自定义日期范围后，预设标签（TD/1M等）取消激活状态
   - 点击预设标签时，日期选择器文本重置为 "Start Date ~ End Date"

7. **Disclaimer**
   - 动态产品名称

**URL 参数**：
- `?type=ppgi&spec=categoryId_width_thickness_材料名称` - 从价格列表页跳转
- `?material=gi` - 旧版兼容

**数据来源**：
- 实时数据通过 REST API `/wp-json/ippgi-prices/v1/price` 获取
- 客户端发送：`productSpec`、`categoryId`、`date`
- 服务器添加 `siteId` 后转发到 api.rendui.com
- 响应数据自动转换为 USD 并缓存

**CSS 样式**：位于 `/assets/css/components.css`
- `.detail-product-info` / `.detail-product-table` - 产品信息表格
- `.detail-realtime` - 实时数据区域
- `.detail-chart-section` - 图表区域
- `.detail-chart__range-tabs` - 时间范围标签

---

## REST API 接口

### 公开接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/prices` | GET | 获取所有材料价格列表 |
| `/wp-json/ippgi-prices/v1/price` | GET | 获取特定规格实时价格 |
| `/wp-json/ippgi-prices/v1/statistics` | GET | 获取价格历史统计（用于 TD 图表，从外部 API） |
| `/wp-json/ippgi-prices/v1/historical` | GET | 获取历史价格数据（用于 1M-4Y 图表，从本地数据库） |

**实时价格接口参数** (`/price`)：
| 参数 | 必填 | 说明 |
|------|------|------|
| `productSpec` | 是 | 完整产品规格（如 `1482328115005964290_1000_0.11_彩涂`） |
| `categoryId` | 是 | 分类 ID |
| `date` | 否 | 日期（YYYY-MM-DD 格式，默认今天） |

**数据流程**：
1. 客户端发送 `productSpec`、`categoryId`、`date`
2. 服务器添加 `siteId`，检查缓存（键：`md5(productSpec + '_' + date)`）
3. 缓存未命中则转发请求到 `api.rendui.com`
4. 响应数据自动进行货币转换（CNY → USD）
5. 转换后数据缓存并返回

**统计数据接口参数** (`/statistics`)：
| 参数 | 必填 | 说明 |
|------|------|------|
| `productSpec` | 是 | 完整产品规格 |
| `categoryId` | 是 | 分类 ID |
| `from` | 是 | 开始时间（YYYY-MM-DD HH:MM:SS 格式） |
| `to` | 是 | 结束时间（YYYY-MM-DD HH:MM:SS 格式） |

**统计数据流程**：
1. 客户端发送 `productSpec`、`categoryId`、`from`、`to`
2. 服务器生成缓存键：`ippgi_stats_` + `md5(productSpec + '_' + from + '_' + to)`
3. 缓存命中直接返回（带 `cached: true` 标识）
4. 缓存未命中则添加 `siteId` 和 `phone` 头转发到 `api.rendui.com/v1/jec/rendui/prices/statistics`
5. 响应数据自动进行货币转换（CNY → USD），包括 `list` 数组和 `rangeAvgPrice`
6. 转换后数据缓存 1 小时（3600 秒）并返回

**历史数据接口参数** (`/historical`)：
| 参数 | 必填 | 说明 |
|------|------|------|
| `productSpec` | 是 | 完整产品规格 |
| `category` | 是 | 产品分类名称（GI、GL、PPGI、HRC、CRC Hard、AL） |
| `range` | 否* | 预设时间范围（1m、6m、1y、2y、3y、4y） |
| `from` | 否* | 自定义开始日期（YYYY-MM-DD 格式） |
| `to` | 否* | 自定义结束日期（YYYY-MM-DD 格式） |

*注：`range` 或 `from`+`to` 二选一必填

**历史数据流程**：
1. 客户端发送 `productSpec`、`category`，以及 `range` 或 `from`+`to`
2. **同一天检测**：如果 `from` 和 `to` 是同一天，转发到外部 statistics API（09:00-17:00 时间范围）
3. 服务器计算日期范围：
   - 预设模式：根据 range 计算（如 4y = 4 年前的今天到今天）
   - 自定义模式：使用 from 和 to 参数（to 不能超过今天）
4. 生成缓存键：
   - 预设模式：`ippgi_hist_` + `md5(productSpec + '_' + category + '_' + range)`
   - 自定义模式：`ippgi_hist_` + `md5(productSpec + '_' + category + '_' + from + '_' + to)`
5. 缓存命中直接返回
6. 缓存未命中则查询本地数据库表（`ippgi_prices_{category}`）
7. **日期填充**：遍历日期范围内的每一天
   - 有数据的日期：使用实际价格
   - 无数据但在首条数据之后（周末/节假日）：沿用前一天价格
   - 无数据且在首条数据之前（产品不存在时期）：价格为 0
8. 转换后数据缓存 1 小时并返回

### 管理员接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/cache-stats` | GET | 缓存统计 |
| `/wp-json/ippgi-prices/v1/clear-cache` | POST | 清除缓存 |
| `/wp-json/ippgi-prices/v1/manual-update` | POST | 手动触发更新 |

---

## 定时任务系统

**时区**：所有时间均为北京时间（Asia/Shanghai，UTC+8）

### 凌晨 00:00 任务流程（北京时间）
1. **保存昨日数据**
   - 读取缓存的价格列表（昨日 17:00 的数据）
   - 从缓存数据中提取汇率（`exchange_rate` 字段）
   - 保存汇率到 `ippgi_prices_exchange_rates` 表
   - 保存价格数据到各材料表

**注意**：00:00 任务只保存数据，不清除缓存也不获取新数据。缓存中保留的是 17:00 的数据，供 00:00-09:00 期间使用。

### 09:00-17:00 每小时更新（北京时间，共9次）
1. 清除所有缓存
2. 从外部 API 获取最新价格数据
3. 重新缓存数据

### 时区实现说明
- 使用 `wp_timezone()` 和 `DateTime` 对象计算正确的 Unix 时间戳
- 避免使用 `current_time('timestamp')`（返回伪时间戳，会导致时区计算错误）
- 相关代码位于 `/wp-content/plugins/ippgi-prices/includes/class-scheduler.php`

---

## 客户端日期请求逻辑

**适用页面**：首页价格表、价格详情页

**逻辑说明**：
- 北京时间 9:00 之前：请求参数 `date` 使用**昨天**的日期
- 北京时间 9:00 及之后：请求参数 `date` 使用**今天**的日期

**原因**：
- 9:00 之前外部 API 可能还没有今天的数据
- 缓存中保留的是昨天 17:00 的数据
- 确保用户始终能看到有效的价格数据

**实现位置**：
- 首页：`/assets/js/main.js` 中的 `getApiDate()` 函数
- 价格详情页：`/page-templates/page-price-detail.php` 中的内联 `getApiDate()` 函数

**服务端支持**：
- `/prices/category` 端点支持 `date` 参数
- `class-api-client.php` 中 `get_price_list($date)` 和 `fetch_price_list($force_refresh, $date)` 支持日期参数
- 缓存键不含日期（`ippgi_prices_price_list`），依赖定时任务时机保证数据一致性

---

## 外部 API 集成

### 价格数据 API
- **价格列表**：`GET https://api.rendui.com/v1/jec/rendui/prices/daily`
- **实时价格**：`POST https://api.rendui.com/v1/jec/rendui/daily/getByProductSpecAndDate`
- **历史数据**：`GET https://api.rendui.com/v1/jec/rendui/prices/statistics`

### 汇率数据 API
- **当前汇率**：中国银行官网 `https://www.boc.cn/sourcedb/whpj/`
- **历史汇率**：欧洲央行 `https://www.frankfurter.app/`

---

## 开发进度

### Phase 1 - 已完成 ✅

#### 1. 核心插件开发 ✅
- 数据库表管理、API 客户端、缓存管理、REST API、定时任务、货币转换、历史数据导入

#### 2. 历史数据导入 ✅
- 价格数据：475,109 条（2022-2026年）
- 汇率数据：1,479 条（日级别精度）

#### 3. 定时任务系统 ✅
- 凌晨保存数据、工作时间每小时更新

#### 4. 主题开发 ✅
- 响应式设计（移动端优先）
- 首页、价格列表、价格详情、订阅、个人资料、收藏夹、登录、支付、邀请
- 博客列表、文章详情、搜索结果
- 服务条款、隐私政策、联系我们

#### 5. 会员系统集成 ✅
- Simple Membership Plugin 集成
- 会员等级权限控制
- 邀请奖励系统（3天 Plus 会员）

#### 6. 首页功能 ✅
- 价格表无限循环左右滑动轮播（5秒间隔，带指示点）、Banner 轮播、Market Insights

#### 7. 博客功能 ✅
- 博客列表页（home.php）
- 日期范围筛选
- 搜索功能

#### 8. 定时任务时区修复 ✅
- 修复 WP-Cron 定时任务时区问题，使用 `wp_timezone()` 和 `DateTime` 正确计算北京时间
- 午夜数据保存：北京时间 00:00
- 每小时刷新：北京时间 09:00-17:00

#### 9. 价格列表页面重新设计 ✅
- 根据 Figma 设计稿完全重写 `/prices/` 页面
- 产品下拉选择器、宽度筛选标签、价格表格
- 动态 Key Attributes（厚度/宽度范围从缓存获取）
- 支持 `?type=` 和 `?category=` URL 参数
- 本地数据切换（无需 API 调用）

#### 10. 价格详情页面重新设计 ✅
- 根据 Figma 设计稿完全重写 `/price-detail/` 页面
- 产品信息表格、实时数据区域、图表区域占位
- 从缓存获取当前价格和涨跌数据
- 时间范围标签（TD、1M、6M、1Y、2Y、3Y、4Y）

#### 11. 实时价格 API 重构 ✅
- 重构数据流：客户端 → 服务器 REST API → 缓存检查 → api.rendui.com
- 缓存键包含 `productSpec` 和 `date`（`md5(productSpec + '_' + date)`）
- 自动货币转换（CNY → USD），支持含税/不含税价格
- 处理 API 响应边缘情况（如 `lastYearsDiff` 为 "-" 字符串）

#### 12. 价格详情页收藏功能 ✅
- 心形收藏按钮：已收藏红色实心，未收藏空心轮廓
- AJAX 切换收藏状态，Toast 提示反馈（2秒）
- 收藏 ID 格式统一：`type-productSpec`
- 修复 My Favorites 页面显示问题（类型键映射：crc→crc_hard, aluminum→al）

#### 13. 实时数据字段映射 ✅
- 主价格：`lastprice_usd` / `lastpriceTax_usd`
- 涨跌值：`riseAndFall_usd` / `riseAndFallTax_usd`
- 涨跌百分比：`riseRange` / `riseRangeTax`（直接使用，已是百分比）
- 平均价：`price_usd` / `priceTax_usd`
- WoW/MoM/YoY：对应 `lastWeekDiff`、`lastMonthDiff`、`lastYearsDiff` 字段

#### 14. 午夜定时任务优化 ✅
- 00:00 任务仅保存数据，移除了缓存清除和获取新数据步骤
- 保留 17:00 缓存数据供 00:00-09:00 期间使用

#### 15. 客户端日期请求逻辑 ✅
- 首页价格表和价格详情页统一实现日期计算逻辑
- 北京时间 9:00 之前请求昨天的日期，9:00 及之后请求今天的日期
- `/prices/category` REST API 端点新增 `date` 参数支持
- `class-api-client.php` 的 `get_price_list()` 和 `fetch_price_list()` 支持日期参数传递

#### 16. 价格图表 - TD（当天）数据 ✅
- `/statistics` REST API 端点：转发请求到 `api.rendui.com/v1/jec/rendui/prices/statistics`
- 服务端自动进行货币转换（CNY → USD），包含 `price`、`priceTax`、`rangeAvgPrice`
- 缓存策略：键为 `ippgi_stats_` + `md5(productSpec + '_' + from + '_' + to)`，过期时间 1 小时
- 客户端 TD 标签逻辑：北京时间 9:00 之前不请求数据；9:00 之后请求当天 00:00:00 ~ 当前时间的统计数据
- Canvas 绘制价格走势图：X 轴为时间戳，Y 轴为价格（USD）
- 时间范围标签（TD、1M、6M、1Y、2Y、3Y、4Y）支持点击切换，带滑动动画效果

---

### Phase 2 - 待实现

1. 价格历史图表 - 1M/6M/1Y/2Y/3Y/4Y 时间范围的历史数据图表（TD 已完成）
2. 历史数据表格 - 从数据库加载历史价格表格
3. 数据导出功能 - Plus 会员专属
4. 价格提醒功能 - 价格变动通知
5. 趋势分析 - 价格趋势分析工具
6. 邮件通知系统 - Plus 会员欢迎邮件等

---

## 部署指南

### 服务器要求
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Nginx 或 Apache
- SSL 证书（推荐）

### 部署步骤

#### 1. 准备云服务器
```bash
# 安装 LNMP/LAMP 环境
# 创建数据库和用户
```

#### 2. 导出本地数据
```bash
# 导出完整数据库
mysqldump -u root -p wordpress > ippgi_full_backup.sql

# 或只导出价格数据表
mysqldump -u root -p wordpress \
  ippgi_prices_gi \
  ippgi_prices_gl \
  ippgi_prices_ppgi \
  ippgi_prices_hrc \
  ippgi_prices_crc_hard \
  ippgi_prices_al \
  ippgi_prices_exchange_rates \
  > ippgi_prices_data.sql
```

#### 3. 上传文件
- 上传整个 WordPress 目录到服务器
- 或使用 Git 部署主题和插件

#### 4. 导入数据库
```bash
mysql -u username -p database_name < ippgi_full_backup.sql
```

#### 5. 配置 wp-config.php
- 更新数据库连接信息
- 更新站点 URL
- 关闭开发模式：`IPPGI_DEV_MODE` 设为 `false`

#### 6. 配置定时任务
```bash
# 添加 crontab 确保 WP-Cron 正常运行
*/5 * * * * curl -s https://yoursite.com/wp-cron.php > /dev/null 2>&1
```

---

## 开发注意事项
- 价格数据展示是核心功能，需要考虑表格在移动端的展示方式
- 内容权限控制需要精细到部分内容级别（同一页面部分可见）
- **缓存策略**：缓存永不过期，由定时任务在固定时间清除（00:00 和 09:00-17:00）
- 生产环境务必关闭 `IPPGI_DEV_MODE`
- **CSS 版本号**：开发模式下自动使用所有 CSS 文件中最新的修改时间作为版本号

---

## 运维工具

### 历史数据导入工具
**文件**：`/import-missing-days.php`

用于补充缺失的价格数据，支持指定日期范围。

**使用方法**：
```bash
# 查看帮助
php import-missing-days.php --help

# 导入指定日期范围
php import-missing-days.php 2026-01-24 2026-01-27

# 导入单天数据
php import-missing-days.php 2026-01-24

# 导入昨天数据（默认）
php import-missing-days.php
```

**功能**：
- 从外部 API 获取历史价格数据
- 自动获取对应日期的历史汇率
- 将数据保存到数据库

---

## 自定义 Logo 支持

主题支持通过 WordPress Customizer 上传自定义 logo。

**Logo 尺寸**：
- 移动端：最大高度 36px
- 桌面端：最大高度 44px
- 宽度自动按比例缩放

**CSS 选择器**：
- `.site-logo .custom-logo` - WordPress 自定义 logo 图片
- `.site-logo .custom-logo-link` - logo 链接容器
- `.site-logo__text` - 文本 logo（无图片时显示）

**注意**：WordPress 输出的 logo 图片带有内联 `width` 和 `height` 属性，CSS 中使用 `height: auto` 覆盖以确保 `max-height` 生效。

---

## MCP 配置规范
- stdio 类型：使用 command + args
- SSE 类型：必须指定 type: "sse" + url
- HTTP 类型：必须指定 type: "http" + url + headers（可选）
