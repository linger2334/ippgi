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
**当前版本**：1.7.2

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
- `template-functions.php` - 模板函数
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
- ID 格式：`type-spec`（如 `ppgi-0.09*1000`）
- `ippgi_get_user_favorites()` 解析并返回结构化数据

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

---

## REST API 接口

### 公开接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/prices` | GET | 获取所有材料价格列表 |
| `/wp-json/ippgi-prices/v1/price` | GET | 获取特定规格价格（参数：product_type, width, thickness, date） |

### 管理员接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/cache-stats` | GET | 缓存统计 |
| `/wp-json/ippgi-prices/v1/clear-cache` | POST | 清除缓存 |
| `/wp-json/ippgi-prices/v1/manual-update` | POST | 手动触发更新 |

---

## 定时任务系统

### 凌晨 00:00 任务流程
1. **保存昨日数据**
   - 读取缓存的价格列表（昨日 17:00 的数据）
   - 从缓存数据中提取汇率（`exchange_rate` 字段）
   - 保存汇率到 `ippgi_prices_exchange_rates` 表
   - 保存价格数据到各材料表
2. **清除所有缓存**
3. **获取今日价格列表**

### 09:00-17:00 每小时更新（共9次）
1. 清除所有缓存
2. 从外部 API 获取最新价格数据
3. 重新缓存数据

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
- 价格表轮播、Banner 轮播、Market Insights

#### 7. 博客功能 ✅
- 博客列表页（home.php）
- 日期范围筛选
- 搜索功能

---

### Phase 2 - 待实现

1. 价格历史图表 - 交互式价格趋势图表
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
